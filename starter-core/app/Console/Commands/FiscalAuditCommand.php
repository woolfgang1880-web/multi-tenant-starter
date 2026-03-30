<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Auditoría de solo lectura sobre datos fiscales de emisores (tenants).
 */
final class FiscalAuditCommand extends Command
{
    protected $signature = 'fiscal:audit';

    protected $description = 'Lista RFC genéricos, RFC duplicados, regímenes fuera de catálogo e incompletos (solo lectura).';

    public function handle(): int
    {
        $generic = ['XAXX010101000', 'XEXX010101000'];

        $this->info('=== RFC genéricos (bloqueados en alta) ===');
        $allTenants = Tenant::query()->get(['id', 'codigo', 'rfc', 'tipo_contribuyente', 'regimen_fiscal_principal']);
        $genericHits = $allTenants->filter(function (Tenant $t) use ($generic) {
            $u = strtoupper(trim((string) $t->rfc));

            return in_array($u, $generic, true);
        });
        foreach ($genericHits as $t) {
            $this->line(sprintf('id=%s codigo=%s rfc=%s', $t->id, $t->codigo, $t->rfc));
        }
        if ($genericHits->isEmpty()) {
            $this->line('Ninguno.');
        }

        $this->newLine();
        $this->info('=== RFC duplicados (más de un tenant con el mismo RFC) ===');
        $dupes = Tenant::query()
            ->select('rfc', DB::raw('COUNT(*) as c'))
            ->whereNotNull('rfc')
            ->where('rfc', '!=', '')
            ->groupBy('rfc')
            ->having('c', '>', 1)
            ->get();
        foreach ($dupes as $d) {
            $this->line(sprintf('rfc=%s count=%s', $d->rfc, $d->c));
        }
        if ($dupes->isEmpty()) {
            $this->line('Ninguno.');
        }

        $pfCodes = array_map('strval', array_keys(config('regimenes_fiscal.persona_fisica', [])));
        $pmCodes = array_map('strval', array_keys(config('regimenes_fiscal.persona_moral', [])));
        $valid = array_merge($pfCodes, $pmCodes);

        $this->newLine();
        $this->info('=== Regímenes no catalogados (clave 3 dígitos esperada) ===');
        foreach ($allTenants as $t) {
            $reg = (string) ($t->regimen_fiscal_principal ?? '');
            if ($reg === '' || ! preg_match('/^\d{3}$/', $reg) || ! in_array($reg, $valid, true)) {
                $this->line(sprintf('id=%s codigo=%s tipo=%s regimen=%s', $t->id, $t->codigo, $t->tipo_contribuyente, $reg));
            }
        }

        $this->newLine();
        $this->info('=== Incompletos (campos obligatorios mínimos ausentes) ===');
        foreach (Tenant::query()->cursor() as $t) {
            $missing = [];
            if (trim((string) $t->rfc) === '') {
                $missing[] = 'rfc';
            }
            if (trim((string) $t->tipo_contribuyente) === '') {
                $missing[] = 'tipo_contribuyente';
            }
            if (trim((string) $t->origen_datos) === '') {
                $missing[] = 'origen_datos';
            }
            if (trim((string) $t->regimen_fiscal_principal) === '') {
                $missing[] = 'regimen_fiscal_principal';
            }
            if (trim((string) $t->codigo_postal) === '') {
                $missing[] = 'codigo_postal';
            }
            if (trim((string) $t->estado) === '') {
                $missing[] = 'estado';
            }
            if ($t->tipo_contribuyente === 'persona_moral' && trim((string) $t->nombre_fiscal) === '') {
                $missing[] = 'nombre_fiscal';
            }
            if ($t->tipo_contribuyente === 'persona_fisica') {
                if (trim((string) $t->curp) === '') {
                    $missing[] = 'curp';
                }
                if (trim((string) $t->pf_nombre) === '') {
                    $missing[] = 'pf_nombre';
                }
                if (trim((string) $t->municipio) === '') {
                    $missing[] = 'municipio';
                }
            }
            if ($missing !== []) {
                $this->line(sprintf('id=%s codigo=%s faltan: %s', $t->id, $t->codigo, implode(', ', $missing)));
            }
        }

        $this->newLine();
        $this->info('Auditoría finalizada (solo lectura).');

        return self::SUCCESS;
    }
}
