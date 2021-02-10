<?php

namespace App\Jobs;

use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class BuscarPalabraMatriz implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $cacheEstados = array();

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $this->CagarEstadosMemoria();
        $this->CagarNotasMatriz();
    }

    private function CagarNotasMatriz()
    {
        try{
            ini_set('memory_limit', '-1');

            $thefolder = "public/matriz/";
            if ($handler = opendir($thefolder)) {

                while (false !== ($archivo = readdir($handler))) {

                    if ($archivo != "." && $archivo != "..") {
                        echo "Archivo matriz " . $archivo ."\n";
                        $datos_matriz = file_get_contents("public/matriz/" . $archivo);
                        $json_matriz = json_decode($datos_matriz, true);

                        foreach($json_matriz as $news){
                            $this->TaggeoNews($news);
                        }

                        unset($news);
                        unset($json_estado);
                    }
                }
                closedir($handler);
            }
        }
        catch(Exception $ex){
            \Log::error($ex->getMessage());
            throw new Exception($ex->getMessage());
        }
    }

    private function TaggeoNews($news)
    {
        try{
            ini_set('memory_limit', '-1');

            $json_estado = json_decode(json_encode($this->cacheEstados));

            foreach($json_estado as $estado){
                $cadena_contenido = strtolower($news);
                $cadena_estado   = strtolower($estado->estado);

                if ($cadena_estado == $cadena_contenido) {
                    echo $cadena_estado ." => ". $cadena_contenido . "\n";
                    break;
                }
            }

            unset($news);
            unset($json_estado);

            return true;
        }
        catch(Exception $ex){
            \Log::error($ex->getMessage());
            throw new Exception($ex->getMessage());
        }
    }

    private function CagarEstadosMemoria()
    {
        try{
            ini_set('memory_limit', '-1');

            $thefolder = "public/estados/";
            if ($handler = opendir($thefolder)) {

                while (false !== ($archivo = readdir($handler))) {

                    if ($archivo != "." && $archivo != "..") {

                        $datos_estado = file_get_contents("public/estados/" . $archivo);
                        $json_estado = json_decode($datos_estado, true);

                        array_push($this->cacheEstados, $json_estado[0]);

                        echo "Se cargo el archivo " . $archivo." a memoria\n";

                        unset($datos_estado);
                        unset($json_estado);
                    }
                }
                closedir($handler);
            }
        }
        catch(Exception $ex){
            \Log::error($ex->getMessage());
            throw new Exception($ex->getMessage());
        }
    }
}
