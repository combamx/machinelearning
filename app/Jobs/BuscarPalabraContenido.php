<?php

namespace App\Jobs;

use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class BuscarPalabraContenido implements ShouldQueue
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
        try{
            $this->CagarEstados();

            echo "===== Iniciando busqueda en el Contenido ".date("H:i:s")." =====\n";

            $news = "public/news/news-" . date("Y-m-d") . ".json";

            $datos_news = file_get_contents($news);
            $json_news = json_decode($datos_news);

            echo "Total de notas a taggear " . count($json_news) ."\n";

            $json_estado = json_decode(json_encode($this->cacheEstados));

            foreach ($json_news as $news) {
                $this->TaggeoNews($news, $json_estado);
            }

            echo "===== Terminando la busqueda en el Contenido ".date("H:i:s")." =====\n";

            unset($datos_news);
            unset($json_news);
            unset($json_estado);
            unset($this->cacheEstados);
        }
        catch(Exception $ex){
            \Log::error($ex->getMessage());
            throw new Exception($ex->getMessage());
        }
    }

    private function TaggeoNews($news, $json_estado)
    {
        try{
            ini_set('memory_limit', '-1');

            foreach($json_estado as $estado){

                $newNews = "public/taggeo/" . $news->id . "-" . str_replace(array(" ", "á", "é", "í", "ó", "ú", "ñ"), array("-", "a", "e", "i", "o", "u", "n"), strtolower($estado->estado)). "-" . date("Y-m-d-H-i-s") . ".json";

                if (file_exists($newNews)) {
                    //unlink($newNews);
                    break;
                }

                if ($news->content != "") {
                    $cadena_contenido = strtolower($news->content);
                    $cadena_estado   = strtolower($estado->estado);

                    if (strpos($cadena_contenido, $cadena_estado) !== false) {
                        $news->estado = $estado->id;
                        $json_municipios = isset($estado->municipios) ? $estado->municipios : array();

                        foreach ($json_municipios as $municipio) {
                            $cadena_buscada_mun = strtolower($municipio->municipio);
                            $cadena_buscada_est_mun = $cadena_buscada_mun . ", " . $cadena_estado;

                            if (strpos($cadena_contenido, $cadena_buscada_mun) !== false || strpos($cadena_contenido, $cadena_buscada_est_mun) !== false) {
                                $news->municipio = $municipio->id;
                            }

                            $json_asentamientos = $municipio->asentamientos;

                            foreach ($json_asentamientos as $asentamiento) {

                                $cadena_buscada_asen = strtolower($asentamiento->nom_asentamiento);
                                $cadena_buscada_asen_min = strtolower($asentamiento->d_asenta);

                                if (strpos($cadena_contenido, $cadena_buscada_asen) !== false || strpos($cadena_contenido, $cadena_buscada_asen_min) !== false) {
                                    $news->estado = $estado->id;
                                    $news->municipio = $municipio->id;
                                    $news->asentamiento = $asentamiento->id;
                                    $news->cp = $asentamiento->cp;
                                    $news->idPostalCode = $asentamiento->idPostalCode;
                                }
                            }
                        }

                        $json = json_encode($news, JSON_UNESCAPED_UNICODE);
                        file_put_contents($newNews, $json);

                        echo $newNews."\n";
                    }
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

    private function CagarEstados()
    {
        try{
            ini_set('memory_limit', '-1');

            $thefolder = "public/json/";
            if ($handler = opendir($thefolder)) {

                while (false !== ($archivo = readdir($handler))) {

                    if ($archivo != "." && $archivo != "..") {

                        $datos_estado = file_get_contents("public/json/" . $archivo);
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
