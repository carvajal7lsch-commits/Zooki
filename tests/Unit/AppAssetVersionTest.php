<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../../config/App.php';

/** Caché de estáticos: misma versión comercial, distintas construcciones. */
final class AppAssetVersionTest extends TestCase
{
    private string $carpeta;
    private ReflectionProperty $cache;
    private mixed $anterior;

    protected function setUp(): void
    {
        $this->carpeta = sys_get_temp_dir() . '/zooki-assets-' . bin2hex(random_bytes(6));
        mkdir($this->carpeta . '/public/js/modulos', 0777, true);
        mkdir($this->carpeta . '/public/css', 0777, true);
        $this->cache = new ReflectionProperty(App::class, 'versionDocker');
        $this->anterior = $this->cache->getValue();
        $this->cache->setValue(null, null);
    }

    protected function tearDown(): void
    {
        $this->cache->setValue(null, $this->anterior);
        $archivos = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->carpeta, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($archivos as $archivo) {
            if ($archivo->isDir()) rmdir($archivo->getPathname());
            else unlink($archivo->getPathname());
        }
        rmdir($this->carpeta);
    }

    private function version(): string
    {
        return (new ReflectionMethod(App::class, 'versionDelDespliegue'))->invoke(null, $this->carpeta);
    }

    public function testMarcaDeConstruccionYCacheEnMemoria(): void
    {
        file_put_contents($this->carpeta . '/VERSION_BUILD', "a1b2c3d4e5f6\n");
        $this->assertSame(App::VERSION . '-a1b2c3d4e5f6', $this->version());
        file_put_contents($this->carpeta . '/VERSION_BUILD', '20261103T190001Z');
        $this->assertSame(App::VERSION . '-a1b2c3d4e5f6', $this->version());
        $this->cache->setValue(null, null);
        $this->assertSame(App::VERSION . '-20261103T190001Z', $this->version());
    }

    public function testSinMarcaUsaElRecursoMasRecienteIncluidosSubdirectorios(): void
    {
        foreach (['js/base.js' => 1000000000, 'css/base.css' => 1000000100, 'js/modulos/panel.js' => 1000000200] as $ruta => $fecha) {
            file_put_contents($this->carpeta . '/public/' . $ruta, '');
            touch($this->carpeta . '/public/' . $ruta, $fecha);
        }
        $this->assertSame(App::VERSION . '-assets1000000200', $this->version());
        touch($this->carpeta . '/public/css/base.css', 1000000300);
        $this->assertSame(App::VERSION . '-assets1000000200', $this->version());
        $this->cache->setValue(null, null);
        $this->assertSame(App::VERSION . '-assets1000000300', $this->version());
    }

    public function testMarcaInvalidaNoSeIncorporaAlParametroDeVersion(): void
    {
        file_put_contents($this->carpeta . '/VERSION_BUILD', 'build&otro=valor');
        $this->assertSame(App::VERSION . '-assets0', $this->version());
    }

    public function testAssetVersionConservaElContratoDelEntornoActual(): void
    {
        if (file_exists('/.dockerenv')) $this->assertStringStartsWith(App::VERSION . '-', App::assetVersion());
        else $this->assertSame(App::VERSION, App::assetVersion(), 'Fuera de Docker, ZOOKI_PRUEBAS mantiene la versión estable');
    }
}
