<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class BuscarPalabraResena implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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
        ini_set('memory_limit', '-1');

        echo "Buscando en el Reseña\n";

        $archivos = array(
            "public/json/guanajuato.json",
        );

        $news = "public/json/news-".date("Y-m-d").".json";

        $datos_news = file_get_contents($news);
        $json_news = json_decode($datos_news);

        // Leemos el JSON
        foreach($archivos as $archivo){
            echo $archivo."\n";
            $datos_estado = file_get_contents($archivo);
            $json_estado = json_decode($datos_estado);
            $estado = $json_estado[0];

            foreach($json_news as $news){

                if ($news->content != ""){
                    $cadena_contenido = strtolower($news->summary);
                    $cadena_estado   = strtolower($estado->estado);

                    if (strpos($cadena_contenido, $cadena_estado) !== false) {
                        echo $news->id . " se encontro el Estado " . $cadena_estado ."\n";

                        $news->estado = $estado->id;
                        $json_municipios = $estado->municipios;

                        foreach($json_municipios as $municipio){
                            $cadena_buscada_mun = strtolower($municipio->municipio);
                            $cadena_buscada_est_mun = $cadena_buscada_mun . ", " . $cadena_estado;

                            if (strpos($cadena_contenido, $cadena_buscada_mun) !== false || strpos($cadena_contenido, $cadena_buscada_est_mun) !== false){
                                echo $news->id . " se encontro el Municipio " . $cadena_buscada_mun . "\n";
                                $news->municipio = $municipio->id;
                            }

                            $json_asentamientos = $municipio->asentamientos;

                            foreach($json_asentamientos as $asentamiento){

                                $cadena_buscada_asen = strtolower($asentamiento->nom_asentamiento);

                                if (strpos($cadena_contenido, $cadena_buscada_asen) !== false){
                                    echo $news->id . " se encontro el Asentamiento " . $cadena_buscada_asen . "\n";
                                    $news->estado = $estado->id;
                                    $news->municipio = $municipio->id;
                                    $news->asentamiento = $asentamiento->id;
                                    $news->cp = $asentamiento->cp;
                                    $news->idPostalCode = $asentamiento->idPostalCode;
                                }

                            }
                        }

                        //var_dump($news); exit;

                        if (!file_exists($archivo)) {
                            $fh = fopen("public/prueba.txt", 'a+');
                            fwrite($fh, $news->id. " " . $news->content ."\n\n");
                            fclose($fh);
                        }

                    }

                }

            }

            unset($datos_estado);
            unset($json_estado);
        }
    }
}
