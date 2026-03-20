<?php

declare (strict_types=1);
namespace Barryvdh\Dom_Pdf;

use Dompdf\Dompdf;
use Illuminate\Support\Service_Provider as IlluminateServiceProvider;
use Illuminate\Support\Str;
class Service_Provider extends Illuminate_Service_Provider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;
    /**
     * Register the service provider.
     *
     * @throws \Exception
     */
    public function register(): void
    {
        $config_path = __DIR__ . '/../config/dompdf.php';
        $this->merge_config_from($config_path, 'dompdf');
        $this->app->bind('dompdf.options', function (array $app) {
            $defines = $app['config']->get('dompdf.defines');
            if ($defines) {
                $options = [];
                /**
                 * @var string $key
                 * @var mixed $value
                 */
                foreach ($defines as $key => $value) {
                    $key = strtolower(str_replace('DOMPDF_', '', $key));
                    $options[$key] = $value;
                }
            } else {
                $options = $app['config']->get('dompdf.options');
            }
            return $options;
        });
        $this->app->bind('dompdf', function (array $app): \Dompdf\Dompdf {
            $options = $app->make('dompdf.options');
            $dompdf = new Dompdf($options);
            $path = realpath($app['config']->get('dompdf.public_path') ?: base_path('public'));
            if ($path === false) {
                throw new \RuntimeException('Cannot resolve public path');
            }
            $dompdf->set_base_path($path);
            return $dompdf;
        });
        $this->app->alias('dompdf', Dompdf::class);
        $this->app->bind('dompdf.wrapper', fn($app) => new PDF($app['dompdf'], $app['config'], $app['files'], $app['view']));
    }
    /**
     * Check if package is running under Lumen app
     */
    protected function is_lumen(): bool
    {
        return Str::contains($this->app->version(), 'Lumen') === true;
    }
    public function boot(): void
    {
        if (!$this->is_lumen()) {
            $config_path = __DIR__ . '/../config/dompdf.php';
            $this->publishes([$config_path => config_path('dompdf.php')], 'config');
        }
    }
    /**
     * Get the services provided by the provider.
     *
     * @return array<string>
     */
    public function provides(): array
    {
        return ['dompdf', 'dompdf.options', 'dompdf.wrapper'];
    }
}