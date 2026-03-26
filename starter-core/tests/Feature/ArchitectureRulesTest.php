<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * PASO 10 — reglas arquitectónicas automáticas (livianas).
 *
 * Son reglas por convención + patrones de texto para evitar regresiones
 * estructurales sin introducir tooling pesado.
 */
class ArchitectureRulesTest extends TestCase
{
    public function test_controllers_do_not_perform_persistence_or_direct_domain_queries(): void
    {
        $controllersDir = $this->path('app/Http/Controllers');
        $controllerFiles = $this->phpFilesIn($controllersDir);

        $allowedModelImports = [
            $this->path('app/Http/Controllers/Api/V1/Auth/LogoutController.php'),
            $this->path('app/Http/Controllers/Api/V1/Auth/MeController.php'),
        ];

        foreach ($controllerFiles as $file) {
            $content = file_get_contents($file);
            $this->assertNotFalse($content, "No se pudo leer {$file}");

            // Señales de lógica de persistencia en controller.
            $forbiddenSnippets = [
                'DB::',
                '::query(',
                '->save(',
            ];

            foreach ($forbiddenSnippets as $snippet) {
                $this->assertStringNotContainsString(
                    $snippet,
                    $content,
                    "Controller con lógica de persistencia detectada en {$file}: {$snippet}"
                );
            }

            if (! in_array($file, $allowedModelImports, true)) {
                $this->assertStringNotContainsString(
                    'use App\\Models\\',
                    $content,
                    "Controller no permitido con dependencia directa de modelo: {$file}"
                );
            }
        }
    }

    public function test_services_do_not_depend_on_controllers(): void
    {
        foreach ($this->phpFilesIn($this->path('app/Services')) as $file) {
            $content = file_get_contents($file);
            $this->assertNotFalse($content, "No se pudo leer {$file}");

            $this->assertStringNotContainsString(
                'use App\\Http\\Controllers\\',
                $content,
                "Service no debe depender de controllers: {$file}"
            );
        }
    }

    public function test_multi_tenant_models_are_not_queried_outside_services_or_models(): void
    {
        $appFiles = $this->phpFilesIn($this->path('app'));

        $allowedPrefixes = [
            $this->path('app/Services').DIRECTORY_SEPARATOR,
            $this->path('app/Models').DIRECTORY_SEPARATOR,
        ];

        foreach ($appFiles as $file) {
            $isAllowed = false;
            foreach ($allowedPrefixes as $prefix) {
                if (str_starts_with($file, $prefix)) {
                    $isAllowed = true;
                    break;
                }
            }
            if ($isAllowed) {
                continue;
            }

            $content = file_get_contents($file);
            $this->assertNotFalse($content, "No se pudo leer {$file}");

            $this->assertStringNotContainsString(
                'User::query(',
                $content,
                "Acceso potencialmente inseguro a User::query fuera de servicios/modelos: {$file}"
            );
            $this->assertStringNotContainsString(
                'Role::query(',
                $content,
                "Acceso potencialmente inseguro a Role::query fuera de servicios/modelos: {$file}"
            );
        }
    }

    public function test_requests_do_not_depend_on_api_responses(): void
    {
        foreach ($this->phpFilesIn($this->path('app/Http/Requests')) as $file) {
            $content = file_get_contents($file);
            $this->assertNotFalse($content, "No se pudo leer {$file}");

            $this->assertStringNotContainsString(
                'use App\\Support\\Api\\ApiResponse',
                $content,
                "Request no debe depender de ApiResponse: {$file}"
            );
            $this->assertStringNotContainsString(
                'HttpResponseException',
                $content,
                "Request no debe construir respuestas HTTP directamente: {$file}"
            );
        }
    }

    public function test_support_logging_does_not_depend_on_controllers(): void
    {
        foreach ($this->phpFilesIn($this->path('app/Support/Logging')) as $file) {
            $content = file_get_contents($file);
            $this->assertNotFalse($content, "No se pudo leer {$file}");

            $this->assertStringNotContainsString(
                'use App\\Http\\Controllers\\',
                $content,
                "Support/Logging no debe depender de controllers: {$file}"
            );
        }
    }

    /**
     * @return list<string>
     */
    private function phpFilesIn(string $directory): array
    {
        $files = [];
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($it as $fileInfo) {
            /** @var \SplFileInfo $fileInfo */
            if ($fileInfo->isFile() && $fileInfo->getExtension() === 'php') {
                $files[] = $fileInfo->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    private function path(string $relative): string
    {
        return dirname(__DIR__, 2).DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
    }
}

