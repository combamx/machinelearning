<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class BuscarPalabraContenido implements ShouldQueue
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

        echo "Buscando en el Contenido\n";

        $news = "public/news/news-".date("Y-m-d").".json";

        $datos_news = file_get_contents($news);
        $json_news = json_decode($datos_news);
        $count = 1;

        foreach($json_news as $news){

            $thefolder = "public/json/";
            if ($handler = opendir($thefolder)) {

                while (false !== ($file = readdir($handler))) {
                    $archivo = $file;

                    if( $file != "." && $file != ".."){
                        echo $count++ ."; ".$news->id.".- ".$archivo."\n";

                        $datos_estado = file_get_contents("public/json/".$archivo);
                        $json_estado = json_decode($datos_estado);
                        $estado = $json_estado[0];


                        if ($news->content != "" && $news->estado == 0){
                            $cadena_contenido = strtolower($news->content);
                            $cadena_estado   = strtolower($estado->estado);

                            //echo substr_count($cadena_contenido, $cadena_estado) . "\n"; break;

                            if (strpos($cadena_contenido, $cadena_estado) !== false) {
                                //echo $news->id . " se encontro el Estado " . $cadena_estado ."\n";

                                $news->estado = $estado->id;
                                $json_municipios = isset($estado->municipios)?$estado->municipios:array();

                                foreach($json_municipios as $municipio){
                                    $cadena_buscada_mun = strtolower($municipio->municipio);
                                    $cadena_buscada_est_mun = $cadena_buscada_mun . ", " . $cadena_estado;

                                    if (strpos($cadena_contenido, $cadena_buscada_mun) !== false || strpos($cadena_contenido, $cadena_buscada_est_mun) !== false){
                                        //echo $news->id . " se encontro el Municipio " . $cadena_buscada_mun . "\n";
                                        $news->municipio = $municipio->id;
                                    }

                                    $json_asentamientos = $municipio->asentamientos;

                                    foreach($json_asentamientos as $asentamiento){

                                        $cadena_buscada_asen = strtolower($asentamiento->nom_asentamiento);

                                        if (strpos($cadena_contenido, $cadena_buscada_asen) !== false){
                                            //echo $news->id . " se encontro el Asentamiento " . $cadena_buscada_asen . "\n";
                                            $news->estado = $estado->id;
                                            $news->municipio = $municipio->id;
                                            $news->asentamiento = $asentamiento->id;
                                            $news->cp = $asentamiento->cp;
                                            $news->idPostalCode = $asentamiento->idPostalCode;
                                        }

                                    }
                                }

                                //var_dump($news); exit;
                                /*
                                if (!file_exists($archivo)) {
                                    $fh = fopen("public/prueba.txt", 'a+');
                                    fwrite($fh, $news->id. " " . $news->content ."\n\n");
                                    fclose($fh);
                                }
                                */

                                $newNews = "public/news/".$news->id."-".$estado->estado."-".date("Y-m-d-i-s").".json";

                                if (file_exists($newNews)) {
                                    unlink($newNews);
                                }

                                $json = json_encode($news, JSON_UNESCAPED_UNICODE);
                                file_put_contents($newNews, $json);

                            }
                        }

                    }
                }
                closedir($handler);
            }

        }

        unset($datos_estado);
        unset($json_estado);

        // Leemos el JSON




    }
}
