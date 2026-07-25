<?php

namespace App;

/**
 * Diff línea a línea entre dos textos, sin dependencias externas (F1).
 * Usa la subsecuencia común más larga (LCS) para producir una lista de
 * operaciones: 'igual', 'add' (añadida en la nueva), 'del' (quitada).
 */
class Diff
{
    /**
     * @return array<int, array{tipo:string, texto:string}>
     *   tipo ∈ {'igual','add','del'}
     */
    public static function lineas(string $viejo, string $nuevo): array
    {
        $a = preg_split('/\R/', $viejo);
        $b = preg_split('/\R/', $nuevo);
        $n = count($a);
        $m = count($b);

        // Tabla LCS.
        $lcs = array_fill(0, $n + 1, array_fill(0, $m + 1, 0));
        for ($i = $n - 1; $i >= 0; $i--) {
            for ($j = $m - 1; $j >= 0; $j--) {
                if ($a[$i] === $b[$j]) {
                    $lcs[$i][$j] = $lcs[$i + 1][$j + 1] + 1;
                } else {
                    $lcs[$i][$j] = max($lcs[$i + 1][$j], $lcs[$i][$j + 1]);
                }
            }
        }

        // Reconstrucción.
        $ops = [];
        $i = 0;
        $j = 0;
        while ($i < $n && $j < $m) {
            if ($a[$i] === $b[$j]) {
                $ops[] = ['tipo' => 'igual', 'texto' => $a[$i]];
                $i++;
                $j++;
            } elseif ($lcs[$i + 1][$j] >= $lcs[$i][$j + 1]) {
                $ops[] = ['tipo' => 'del', 'texto' => $a[$i]];
                $i++;
            } else {
                $ops[] = ['tipo' => 'add', 'texto' => $b[$j]];
                $j++;
            }
        }
        while ($i < $n) {
            $ops[] = ['tipo' => 'del', 'texto' => $a[$i]];
            $i++;
        }
        while ($j < $m) {
            $ops[] = ['tipo' => 'add', 'texto' => $b[$j]];
            $j++;
        }

        return $ops;
    }

    /**
     * Cuenta líneas añadidas y quitadas de un conjunto de operaciones.
     * @return array{add:int, del:int}
     */
    public static function resumen(array $ops): array
    {
        $add = 0;
        $del = 0;
        foreach ($ops as $op) {
            if ($op['tipo'] === 'add') {
                $add++;
            } elseif ($op['tipo'] === 'del') {
                $del++;
            }
        }
        return ['add' => $add, 'del' => $del];
    }
}
