<?php

namespace App\Services\Optimizer;

/**
 * High-performance Two-Phase Simplex Linear Programming Solver in pure PHP.
 *
 * Minimizes: c^T * x
 * Subject to:
 *   A_ub * x <= b_ub
 *   A_eq * x == b_eq
 *   lower_i <= x_i <= upper_i
 */
class SimplexSolver
{
    public static function solve(
        array $c,
        array $A_ub,
        array $b_ub,
        array $A_eq,
        array $b_eq,
        array $bounds
    ): ?array {
        $n = count($c);

        // Convert bounded variables: x_i = x'_i + lower_i, where 0 <= x'_i <= (upper_i - lower_i)
        $x_shift = [];
        $var_ub = [];
        for ($i = 0; $i < $n; $i++) {
            $lb = $bounds[$i][0] ?? 0.0;
            $ub = $bounds[$i][1] ?? INF;
            $x_shift[$i] = $lb;
            $var_ub[$i] = is_infinite($ub) ? INF : max(0.0, $ub - $lb);
        }

        // Adjust constraints for shift:
        // A * (x' + shift) <= b  =>  A * x' <= b - A * shift
        $mod_b_ub = $b_ub;
        foreach ($A_ub as $r => $row) {
            foreach ($row as $col => $val) {
                if ($val != 0.0 && $x_shift[$col] != 0.0) {
                    $mod_b_ub[$r] -= $val * $x_shift[$col];
                }
            }
        }

        $mod_b_eq = $b_eq;
        foreach ($A_eq as $r => $row) {
            foreach ($row as $col => $val) {
                if ($val != 0.0 && $x_shift[$col] != 0.0) {
                    $mod_b_eq[$r] -= $val * $x_shift[$col];
                }
            }
        }

        // Add upper bound constraints as inequality constraints: x'_i <= var_ub[i]
        $extra_A_ub = [];
        $extra_b_ub = [];
        for ($i = 0; $i < $n; $i++) {
            if (!is_infinite($var_ub[$i])) {
                $row = array_fill(0, $n, 0.0);
                $row[$i] = 1.0;
                $extra_A_ub[] = $row;
                $extra_b_ub[] = $var_ub[$i];
            }
        }

        $all_A_ub = array_merge($A_ub, $extra_A_ub);
        $all_b_ub = array_merge($mod_b_ub, $extra_b_ub);

        $num_ub = count($all_A_ub);
        $num_eq = count($A_eq);

        $tableau_A = [];

        // Inequality rows
        for ($i = 0; $i < $num_ub; $i++) {
            $row = $all_A_ub[$i];
            $b_val = $all_b_ub[$i];
            if ($b_val >= -1e-9) {
                $tableau_A[] = ['type' => '<=', 'orig_row' => $row, 'b' => max(0.0, $b_val)];
            } else {
                $inv_row = array_map(fn($v) => -$v, $row);
                $tableau_A[] = ['type' => '>=', 'orig_row' => $inv_row, 'b' => -$b_val];
            }
        }

        // Equality rows
        for ($i = 0; $i < $num_eq; $i++) {
            $row = $A_eq[$i];
            $b_val = $mod_b_eq[$i];
            if ($b_val < -1e-9) {
                $row = array_map(fn($v) => -$v, $row);
                $b_val = -$b_val;
            }
            $tableau_A[] = ['type' => '==', 'orig_row' => $row, 'b' => max(0.0, $b_val)];
        }

        $m = count($tableau_A);
        $col_count = $n;
        $slack_indices = [];
        $art_indices = [];
        $basis = [];

        for ($i = 0; $i < $m; $i++) {
            $type = $tableau_A[$i]['type'];
            if ($type === '<=') {
                $slack_col = $col_count++;
                $slack_indices[$i] = $slack_col;
                $basis[$i] = $slack_col;
            } elseif ($type === '>=') {
                $surplus_col = $col_count++;
                $slack_indices[$i] = $surplus_col;
                $art_col = $col_count++;
                $art_indices[$i] = $art_col;
                $basis[$i] = $art_col;
            } elseif ($type === '==') {
                $art_col = $col_count++;
                $art_indices[$i] = $art_col;
                $basis[$i] = $art_col;
            }
        }

        // Row 0: Phase 1 objective
        // Row 1: Phase 2 objective
        // Rows 2 .. m+1: Constraints
        $T = [];
        for ($r = 0; $r < $m + 2; $r++) {
            $T[$r] = array_fill(0, $col_count + 1, 0.0);
        }

        // Fill original objective into Row 1
        for ($j = 0; $j < $n; $j++) {
            $T[1][$j] = $c[$j];
        }

        // Fill constraint rows
        for ($i = 0; $i < $m; $i++) {
            $r = $i + 2;
            $type = $tableau_A[$i]['type'];
            for ($j = 0; $j < $n; $j++) {
                $T[$r][$j] = $tableau_A[$i]['orig_row'][$j];
            }
            if ($type === '<=') {
                $T[$r][$slack_indices[$i]] = 1.0;
            } elseif ($type === '>=') {
                $T[$r][$slack_indices[$i]] = -1.0;
                $T[$r][$art_indices[$i]] = 1.0;
            } elseif ($type === '==') {
                $T[$r][$art_indices[$i]] = 1.0;
            }
            $T[$r][$col_count] = $tableau_A[$i]['b'];
        }

        $has_artificials = count($art_indices) > 0;

        if ($has_artificials) {
            foreach ($art_indices as $row_idx => $art_c) {
                $r = $row_idx + 2;
                for ($col = 0; $col <= $col_count; $col++) {
                    $T[0][$col] -= $T[$r][$col];
                }
            }

            if (!self::pivotLoop($T, $basis, 0, $col_count, $m, true)) {
                return null;
            }

            if (abs($T[0][$col_count]) > 1e-4) {
                return null; // Infeasible
            }

            // Drive out any artificial variables still remaining in the basis
            $art_set = array_flip($art_indices);
            for ($i = 0; $i < $m; $i++) {
                $r = $i + 2;
                $basic_col = $basis[$i];
                if (isset($art_set[$basic_col])) {
                    $found_col = -1;
                    for ($j = 0; $j < $col_count; $j++) {
                        if (!isset($art_set[$j]) && abs($T[$r][$j]) > 1e-9) {
                            $found_col = $j;
                            break;
                        }
                    }
                    if ($found_col !== -1) {
                        $pivot_elem = $T[$r][$found_col];
                        for ($col = 0; $col <= $col_count; $col++) {
                            $T[$r][$col] /= $pivot_elem;
                        }
                        for ($other_r = 0; $other_r < $m + 2; $other_r++) {
                            if ($other_r !== $r) {
                                $factor = $T[$other_r][$found_col];
                                if (abs($factor) > 1e-12) {
                                    for ($col = 0; $col <= $col_count; $col++) {
                                        $T[$other_r][$col] -= $factor * $T[$r][$col];
                                    }
                                }
                            }
                        }
                        $basis[$i] = $found_col;
                    }
                }
            }
        }


        // Phase 2: Eliminate basic columns in Row 1
        for ($i = 0; $i < $m; $i++) {
            $r = $i + 2;
            $basic_col = $basis[$i];
            $factor = $T[1][$basic_col];
            if (abs($factor) > 1e-12) {
                for ($col = 0; $col <= $col_count; $col++) {
                    $T[1][$col] -= $factor * $T[$r][$col];
                }
            }
        }

        if (!self::pivotLoop($T, $basis, 1, $col_count, $m, false, $art_indices)) {
            return null;
        }

        $sol = array_fill(0, $n, 0.0);
        for ($i = 0; $i < $m; $i++) {
            $basic_col = $basis[$i];
            if ($basic_col < $n) {
                $sol[$basic_col] = max(0.0, $T[$i + 2][$col_count]);
            }
        }

        $final_x = [];
        for ($i = 0; $i < $n; $i++) {
            $final_x[$i] = $sol[$i] + $x_shift[$i];
        }

        return $final_x;
    }

    private static function pivotLoop(
        array &$T,
        array &$basis,
        int $obj_row,
        int $num_cols,
        int $num_constraints,
        bool $phase1,
        array $art_indices = []
    ): bool {
        $art_set = array_flip($art_indices);
        $max_iter = 10000;
        $iter = 0;

        while ($iter++ < $max_iter) {
            $pivot_col = -1;
            $min_val = -1e-9;
            for ($j = 0; $j < $num_cols; $j++) {
                if (!$phase1 && isset($art_set[$j])) {
                    continue;
                }
                if ($T[$obj_row][$j] < $min_val) {
                    $min_val = $T[$obj_row][$j];
                    $pivot_col = $j;
                }
            }

            if ($pivot_col === -1) {
                return true;
            }

            $pivot_row = -1;
            $min_ratio = INF;
            for ($i = 0; $i < $num_constraints; $i++) {
                $r = $i + 2;
                $elem = $T[$r][$pivot_col];
                if ($elem > 1e-9) {
                    $ratio = max(0.0, $T[$r][$num_cols]) / $elem;
                    if ($ratio < $min_ratio - 1e-12) {
                        $min_ratio = $ratio;
                        $pivot_row = $r;
                    }
                }
            }

            if ($pivot_row === -1) {
                return false;
            }

            $pivot_elem = $T[$pivot_row][$pivot_col];
            for ($j = 0; $j <= $num_cols; $j++) {
                $T[$pivot_row][$j] /= $pivot_elem;
            }

            $total_rows = $num_constraints + 2;
            for ($r = 0; $r < $total_rows; $r++) {
                if ($r !== $pivot_row) {
                    $factor = $T[$r][$pivot_col];
                    if (abs($factor) > 1e-12) {
                        for ($j = 0; $j <= $num_cols; $j++) {
                            $T[$r][$j] -= $factor * $T[$pivot_row][$j];
                        }
                    }
                }
            }

            $basis[$pivot_row - 2] = $pivot_col;
        }

        return false;
    }
}
