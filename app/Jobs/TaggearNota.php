<?php

namespace App\Jobs;

use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class TaggearNota implements ShouldQueue
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
            $this->CagarEstadosMemoria();
            $this->InicioTaggeoNotas();
            //$this->ActualizarNoticiaTaggeada();
        }
        catch(Exception $ex){
            \Log::error($ex->getMessage());
            throw new Exception($ex->getMessage());
        }
    }

    private function InicioTaggeoNotas(){
        try{
            echo "===== Iniciando busqueda en el Titulo ".date("H:i:s")." =====\n";

            $news = "public/news/news-" . date("Y-m-d") . ".json";

            echo $news."\n";

            if (!file_exists($news)) {
                echo "El archivo " . $news ." no existe\n";
            }

            $datos_news = file_get_contents($news);
            $json_news = json_decode($datos_news);

            echo "Total de notas a taggear " . count($json_news) ."\n";

            $json_estado = json_decode(json_encode($this->cacheEstados));

            foreach ($json_news as $news) {
                $this->TaggeoNews($news, $json_estado);
            }

            echo "===== Terminando la busqueda en el Titulo ".date("H:i:s")." =====\n";

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
            $count = 1;

            $taggeoPor = "";

            foreach($json_estado as $estado){

                $cadena_title = strtolower($news->title);
                $cadena_resena = strtolower($news->summary);
                $cadena_contenido = strtolower($news->content);
                $cadena_estado   = strtolower($estado->estado);

                if (strpos($cadena_title, $cadena_estado) !== false || strpos($cadena_resena, $cadena_estado) !== false) {
                    $newNews = "public/taggeo/" . $news->id . "-tituloresena-" . str_replace(array(" ", "á", "é", "í", "ó", "ú", "ñ"), array("-", "a", "e", "i", "o", "u", "n"), strtolower($estado->estado)). "-" . date("Y-m-d-H-i-s") . ".json";

                    if (file_exists($newNews)) {
                        unlink($newNews);
                        //break;
                    }

                    $news->estado = $estado->estado;
                    $json_municipios = isset($estado->municipios) ? $estado->municipios : array();

                    foreach ($json_municipios as $municipio) {
                        $cadena_buscada_mun = strtolower($municipio->municipio);
                        $cadena_buscada_est_mun = $cadena_buscada_mun . ", " . $cadena_estado;

                        if ( strpos($cadena_title, $cadena_buscada_mun) !== false || strpos($cadena_title, $cadena_buscada_est_mun) !== false ||
                                strpos($cadena_resena, $cadena_buscada_mun) !== false || strpos($cadena_resena, $cadena_buscada_est_mun) !== false ){
                            $news->municipio = $municipio->municipio;

                            $json_asentamientos = $municipio->asentamientos;

                            foreach ($json_asentamientos as $asentamiento) {

                                $cadena_buscada_asen = strtolower($asentamiento->nom_asentamiento);
                                $cadena_buscada_asen_min = strtolower($asentamiento->d_asenta);

                                if (strpos($cadena_title, $cadena_buscada_asen) !== false || strpos($cadena_title, $cadena_buscada_asen_min) !== false) {
                                    $news->estado = $estado->estado;
                                    $news->municipio = $municipio->municipio;
                                    $news->asentamiento = $asentamiento->id;
                                    $news->cp = $asentamiento->cp;
                                    $news->idPostalCode = $asentamiento->idPostalCode;
                                }
                            }
                        }
                    }

                    $json = json_encode($news, JSON_UNESCAPED_UNICODE);
                    file_put_contents($newNews, $json);

                    echo $newNews."\n";
                }
                else if($news->content != ""){
                    if (strpos($cadena_contenido, $cadena_estado) !== false) {

                        $newNews = "public/taggeo/" . $news->id . "-contenido-" . str_replace(array(" ", "á", "é", "í", "ó", "ú", "ñ"), array("-", "a", "e", "i", "o", "u", "n"), strtolower($estado->estado)). "-" . date("Y-m-d-H-i-s") . ".json";

                        if (file_exists($newNews)) {
                            unlink($newNews);
                            //break;
                        }

                        $news->estado = $estado->estado;
                        $json_municipios = isset($estado->municipios) ? $estado->municipios : array();

                        foreach ($json_municipios as $municipio) {
                            $cadena_buscada_mun = strtolower($municipio->municipio);
                            $cadena_buscada_est_mun = $cadena_buscada_mun . ", " . $cadena_estado;

                            if (strpos($cadena_contenido, $cadena_buscada_mun) !== false || strpos($cadena_contenido, $cadena_buscada_est_mun) !== false) {
                                $news->municipio = $municipio->municipio;

                                $json_asentamientos = $municipio->asentamientos;

                                foreach ($json_asentamientos as $asentamiento) {

                                    $cadena_buscada_asen = strtolower($asentamiento->nom_asentamiento);
                                    $cadena_buscada_asen_min = strtolower($asentamiento->d_asenta);

                                    if (strpos($cadena_contenido, $cadena_buscada_asen) !== false || strpos($cadena_contenido, $cadena_buscada_asen_min) !== false) {
                                        $news->estado = $estado->estado;
                                        $news->municipio = $municipio->municipio;
                                        $news->asentamiento = $asentamiento->id;
                                        $news->cp = $asentamiento->cp;
                                        $news->idPostalCode = $asentamiento->idPostalCode;
                                    }
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

    private function CagarEstadosMemoria()
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

    private function ActualizarNoticiaTaggeada(){
        try{
            ini_set('memory_limit', '-1');

            $thefolder = "public/taggeo/";
            if ($handler = opendir($thefolder)) {

                while (false !== ($archivo = readdir($handler))) {

                    if ($archivo != "." && $archivo != "..") {

                        $datos_news_taggeo = file_get_contents("public/taggeo/" . $archivo);
                        $json_news_taggeo = json_decode($datos_news_taggeo);

                        $updated_at = date('Y-m-d h:i:s');
                        $editor = \DB::select("SELECT id FROM admin_users WHERE username = 'newbotleon';");

                        $copoModel = $json_news_taggeo;

                        $url = $archivo . "    => /" . $this->slugify($copoModel->estado) . "/" . $this->slugify($copoModel->municipio) . "/" . $copoModel->cp . "/" . $this->slugify($copoModel->title);
                        $url_copo = $this->unwantedArray($url);

                        echo $url."\n";

                        unset($datos_estado);
                        unset($json_estado);
                    }
                }
                closedir($handler);
            }


            /*
                \DB::table('news')
                ->where("id", $check[0]->id)
                ->update(
                    [
                        "id_cp" => $this->idPostalCodeAutomatic,
                        "url" => $url,
                        "url_copo" => $url_copo,
                        "id_status_news" => 3,
                        "id_editor" => $editor[0]->id,
                        "updated_at" => $this->updated_at
                    ]
                );

                \DB::table('news_auto')
                ->insert(
                    [
                        "id_news" => $check[0]->id,
                        "estado" => $this->states,
                        "municipio" => $this->municipality,
                        "asentamiento" => $this->state,
                        "puntuacion" => $this->score
                    ]
                );
            */
        }
        catch(Exception $ex){
            \Log::error($ex->getMessage());
            throw new Exception($ex->getMessage());
        }
    }

    public function slugify($text)
    {
        // replace non letter or digits by -
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);

        // transliterate
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);

        // remove unwanted characters
        $text = preg_replace('~[^-\w]+~', '', $text);

        // trim
        $text = trim($text, '-');

        // remove duplicate -
        $text = preg_replace('~-+~', '-', $text);

        // lowercase
        $text = strtolower($text);

        if (empty($text)) {
            return 'n-a';
        }

        return $text;
    }

    public function unwantedArray($str)
    {
        $unwanted_array = array(
            'Š' => 'S', 'š' => 's', 'Ž' => 'Z', 'ž' => 'z', 'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A', 'Æ' => 'A', 'Ç' => 'C', 'È' => 'E', 'É' => 'E',
            'Ê' => 'E', 'Ë' => 'E', 'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I', 'Ñ' => 'N', 'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O', 'Ø' => 'O', 'Ù' => 'U',
            'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U', 'Ý' => 'Y', 'Þ' => 'B', 'ß' => 'Ss', 'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a', 'æ' => 'a', 'ç' => 'c',
            'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e', 'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i', 'ð' => 'o', 'ñ' => 'n', 'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o',
            'ö' => 'o', 'ø' => 'o', 'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ý' => 'y', 'þ' => 'b', 'ÿ' => 'y'
        );
        return strtr($str, $unwanted_array);
    }
}
