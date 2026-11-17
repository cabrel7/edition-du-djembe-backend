<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Casts\LenientJsonArray;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Rattrape les colonnes JSON doublement encodées (memoirs.keywords, team_members.social_links).
 * Dry-run par défaut ; --apply pour écrire. Idempotent, sans perte : une valeur n'est
 * réécrite que si le décodage donne un tableau et que la forme stockée diffère.
 *
 *   php artisan app:fix-double-encoded-json [--apply]
 */
class FixDoubleEncodedJson extends Command
{
    protected $signature = 'app:fix-double-encoded-json {--apply : Écrit les corrections (sinon dry-run)}';

    protected $description = 'Corrige les valeurs JSON doublement encodées (dry-run par défaut)';

    private const TARGETS = [
        'memoirs' => 'keywords',
        'team_members' => 'social_links',
    ];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $total = 0;

        foreach (self::TARGETS as $table => $column) {
            $total += $this->process($table, $column, $apply);
        }

        $this->info(sprintf('%d ligne(s) %s.', $total, $apply ? 'corrigée(s)' : 'à corriger (dry-run, relancer avec --apply)'));

        return self::SUCCESS;
    }

    private function process(string $table, string $column, bool $apply): int
    {
        $count = 0;
        DB::table($table)->whereNotNull($column)->orderBy('id')->select(['id', $column])
            ->chunkById(200, function ($rows) use ($table, $column, $apply, &$count): void {
                foreach ($rows as $row) {
                    $raw = $row->{$column};
                    if (!is_string($raw) || !$this->isDoubleEncoded($raw)) {
                        continue;
                    }
                    $fixed = json_encode(LenientJsonArray::decode($raw), JSON_THROW_ON_ERROR);
                    $count++;
                    $this->line(sprintf('%s#%d.%s', $table, $row->id, $column));
                    if ($apply) {
                        DB::table($table)->where('id', $row->id)->update([$column => $fixed]);
                    }
                }
            });

        return $count;
    }

    /** Vrai uniquement si le premier décodage donne une chaîne qui décode elle-même en tableau. */
    private function isDoubleEncoded(string $raw): bool
    {
        $first = json_decode($raw, true);

        return is_string($first) && LenientJsonArray::decode($first) !== null;
    }
}
