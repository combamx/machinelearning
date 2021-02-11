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
    private $notasTaggeadas = array();
    private $notas = null;

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
        try {
            $this->CagarEstadosMemoria();
            $this->InicioTaggeoNotas();
            //$this->ActualizarNoticiaTaggeada();
        } catch (Exception $ex) {
            \Log::error($ex->getMessage());
            throw new Exception($ex->getMessage());
        }
    }

    private function InicioTaggeoNotas()
    {
        try {
            echo "===== Iniciando busqueda en el Titulo " . date("H:i:s") . " =====\n";

            $news = "public/news/news-" . date("Y-m-d") . ".json";
            echo $news . "\n";

            if (!file_exists($news)) {
                echo "El archivo " . $news . " no existe\n";
                return false;
            }

            $directorio = "public/taggeo/" . date("Y") . "/" . date("m") . "/" . date("d") . "/";

            if (!file_exists($directorio)) {
                mkdir($directorio, 0777, true);
                chmod($directorio, 0777);
            }

            if (file_exists($directorio . "notaTaggeada.txt")) unlink($directorio . "notaTaggeada.txt");
            if (file_exists($directorio . "no-notaTaggeada.txt")) unlink($directorio . "no-notaTaggeada.txt");

            $datos_news = file_get_contents($news);
            $json_news = json_decode($datos_news);
            echo "Total de notas a taggear " . count($json_news) . "\n";
            $json_estado = json_decode(json_encode($this->cacheEstados));

            foreach ($json_news as $news) {
                $this->TaggeoNews($news, $json_estado);
            }

            echo "===== Terminando la busqueda en el Titulo " . date("H:i:s") . " =====\n";

            unset($datos_news);
            unset($json_news);
            unset($json_estado);
            unset($this->cacheEstados);
        } catch (Exception $ex) {
            \Log::error($ex->getMessage());
            throw new Exception($ex->getMessage());
        }
    }

    private function TaggeoNews($news, $json_estado)
    {
        try {
            ini_set('memory_limit', '-1');

            $palabrasNo = array("juárez %s more", "%s lópez", "%s more", "miguel %s", "david vicenteño%s", "viral en %s");
            $directorio = "public/taggeo/" . date("Y") . "/" . date("m") . "/" . date("d") . "/";

            $this->notas = $news;

            foreach ($json_estado as $estado) {

                $cadena_title = strtolower($news->title);
                $cadena_resena = strtolower($news->summary);
                $cadena_contenido = strtolower($news->content);
                $cadena_estado   = strtolower($estado->estado);

                foreach ($palabrasNo as $item) {
                    $item = sprintf($item, $cadena_estado);
                    if (strpos($cadena_resena, $item) !== false || strpos($cadena_title, $item) !== false) {
                        break;
                    } else if ($news->content != "" && strpos($cadena_contenido, $item) !== false) {
                        break;
                    }

                    if (strpos($cadena_title, $cadena_estado) !== false || strpos($cadena_resena, $cadena_estado) !== false) {
                        $newNews = $directorio . $news->id . ".json";

                        if (file_exists($newNews)) {
                            unlink($newNews);
                            //break;
                        }

                        $news->estado = $estado->estado;
                        $json_municipios = isset($estado->municipios) ? $estado->municipios : array();

                        foreach ($json_municipios as $municipio) {
                            $cadena_buscada_mun = strtolower($municipio->municipio);
                            $cadena_buscada_est_mun = $cadena_buscada_mun . ", " . $cadena_estado;

                            if (strpos($cadena_title, $cadena_buscada_mun) !== false || strpos($cadena_title, $cadena_buscada_est_mun) !== false || strpos($cadena_resena, $cadena_buscada_mun) !== false || strpos($cadena_resena, $cadena_buscada_est_mun) !== false) {
                                $news->municipio = $municipio->municipio;

                                $json_asentamientos = $municipio->asentamientos;

                                foreach ($json_asentamientos as $asentamiento) {

                                    $cadena_buscada_asen = strtolower($asentamiento->nom_asentamiento);
                                    $cadena_buscada_asen_min = strtolower($asentamiento->d_asenta);

                                    if (strpos($cadena_title, $cadena_buscada_asen) !== false || strpos($cadena_title, $cadena_buscada_asen_min) !== false) {
                                        //$news->estado = $estado->estado;
                                        //$news->municipio = $municipio->municipio;
                                        $news->asentamiento = $asentamiento->nom_asentamiento;
                                        $news->cp = $asentamiento->cp;
                                        $news->idPostalCode = $asentamiento->idPostalCode;
                                        $news->copo = $asentamiento->copo;
                                    }
                                }
                            }
                        }

                        $json = json_encode($news, JSON_UNESCAPED_UNICODE);
                        file_put_contents($newNews, $json);
                        array_push($this->notasTaggeadas, $news->id);
                        echo $newNews . "\n";

                        $dato = $news->id . "|" . $news->estado . "|" . $news->municipio . "|" . $news->asentamiento . "|" . $news->cp . "|" . $news->idPostalCode . "|" . $news->copo . "|titulo o reseña\n";

                        $fh = fopen($directorio . "notaTaggeada.txt", "a+") or die("Se produjo un error al crear el archivo");
                        fwrite($fh, $dato) or die("No se pudo escribir en el archivo");
                        fclose($fh);

                        break;
                    } else if ($news->content != "") {
                        if (strpos($cadena_contenido, $cadena_estado) !== false) {
                            $newNews = $directorio . $news->id . ".json";

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
                                            //$news->estado = $estado->estado;
                                            //$news->municipio = $municipio->municipio;
                                            $news->asentamiento = $asentamiento->nom_asentamiento;
                                            $news->cp = $asentamiento->cp;
                                            $news->idPostalCode = $asentamiento->idPostalCode;
                                            $news->copo = $asentamiento->copo;
                                        }
                                    }
                                }
                            }

                            $json = json_encode($news, JSON_UNESCAPED_UNICODE);
                            file_put_contents($newNews, $json);
                            echo $newNews . "\n";

                            $dato = $news->id . "|" . $news->estado . "|" . $news->municipio . "|" . $news->asentamiento . "|" . $news->cp . "|" . $news->idPostalCode . "|" . $news->copo . "|contenido\n";

                            $fh = fopen($directorio . "notaTaggeada.txt", "a+") or die("Se produjo un error al crear el archivo");
                            fwrite($fh, $dato) or die("No se pudo escribir en el archivo");
                            fclose($fh);

                            break;
                        } else {
                            // Buscar por matriz de palabras en cdmx
                            $cdmx = array("ciudad de méxico", "tren maya", "dos bocas", "santa lucía", "amlo", "andrés manuel lópez obrador", "impulsan gasolina y gas", "enfermedades cardiovasculares", "presidente de méxico");
                            $cdmxIztacalco = array("iztacalco");
                            $guerrero = array("chilpancingo");
                            $jalisco = array("zapopan");

                            $array_contenido = explode(" ", $cadena_contenido);

                            foreach($cdmx as $item){
                                $matches = array_filter($array_contenido, function ($var) use ($item) {
                                    $this->notas->estado = "Ciudad de México";
                                    $this->notas->municipio = "Cuauhtémoc";
                                    $this->notas->asentamiento = "Centro (Área 1)";
                                    $this->notas->cp = 6000;
                                    $this->notas->idPostalCode = 506;
                                    $this->notas->copo = "Centro CDMX";

                                    return preg_match("/$item/i", $var);
                                });

                                if ($matches) { // se ha encontrado el termino
                                    $newNews = $directorio . $this->notas->id . ".json";
                                    $json = json_encode($this->notas, JSON_UNESCAPED_UNICODE);
                                    file_put_contents($newNews, $json);
                                    echo $newNews . "\n";

                                    $dato = $this->notas->id . "|" . $this->notas->estado . "|" . $this->notas->municipio . "|" . $this->notas->asentamiento . "|" . $this->notas->cp . "|" . $this->notas->idPostalCode . "|" . $this->notas->copo . "|cdmx\n";

                                    $fh = fopen($directorio . "notaTaggeada.txt", "a+") or die("Se produjo un error al crear el archivo");
                                    fwrite($fh, $dato) or die("No se pudo escribir en el archivo");
                                    fclose($fh);
                                    break;
                                } else { // El termino no se ha encontrado
                                }
                            }

                            foreach($cdmxIztacalco as $item){
                                $matches = array_filter($array_contenido, function ($var) use ($item) {
                                    $this->notas->estado = "Ciudad de México";
                                    $this->notas->municipio = "Iztacalco";
                                    $this->notas->asentamiento = "";
                                    $this->notas->cp = 8030;
                                    $this->notas->idPostalCode = 0;
                                    $this->notas->copo = "";

                                    return preg_match("/$item/i", $var);
                                });

                                if ($matches) { // se ha encontrado el termino
                                    $newNews = $directorio . $this->notas->id . ".json";
                                    $json = json_encode($this->notas, JSON_UNESCAPED_UNICODE);
                                    file_put_contents($newNews, $json);
                                    echo $newNews . "\n";

                                    $dato = $this->notas->id . "|" . $this->notas->estado . "|" . $this->notas->municipio . "|" . $this->notas->asentamiento . "|" . $this->notas->cp . "|" . $this->notas->idPostalCode . "|" . $this->notas->copo . "|iztacalco\n";

                                    $fh = fopen($directorio . "notaTaggeada.txt", "a+") or die("Se produjo un error al crear el archivo");
                                    fwrite($fh, $dato) or die("No se pudo escribir en el archivo");
                                    fclose($fh);
                                    break;
                                } else { // El termino no se ha encontrado
                                }
                            }

                            foreach($guerrero as $item){
                                $matches = array_filter($array_contenido, function ($var) use ($item) {
                                    $this->notas->estado = "Guerrero";
                                    $this->notas->municipio = "Chilpancingo de los Bravo";
                                    $this->notas->asentamiento = "Puesta del Sol";
                                    $this->notas->cp = 39425;
                                    $this->notas->idPostalCode = 46919;
                                    $this->notas->copo = "";

                                    return preg_match("/$item/i", $var);
                                });

                                if ($matches) { // se ha encontrado el termino
                                    $newNews = $directorio . $this->notas->id . ".json";
                                    $json = json_encode($this->notas, JSON_UNESCAPED_UNICODE);
                                    file_put_contents($newNews, $json);
                                    echo $newNews . "\n";

                                    $dato = $this->notas->id . "|" . $this->notas->estado . "|" . $this->notas->municipio . "|" . $this->notas->asentamiento . "|" . $this->notas->cp . "|" . $this->notas->idPostalCode . "|" . $this->notas->copo . "|guerrero\n";

                                    $fh = fopen($directorio . "notaTaggeada.txt", "a+") or die("Se produjo un error al crear el archivo");
                                    fwrite($fh, $dato) or die("No se pudo escribir en el archivo");
                                    fclose($fh);
                                    break;
                                } else { // El termino no se ha encontrado
                                }
                            }

                            foreach($jalisco as $item){
                                $matches = array_filter($array_contenido, function ($var) use ($item) {
                                    $this->notas->estado = "Jalisco";
                                    $this->notas->municipio = "Zapopan";
                                    $this->notas->asentamiento = "";
                                    $this->notas->cp = 45118;
                                    $this->notas->idPostalCode = 0;
                                    $this->notas->copo = "";

                                    return preg_match("/$item/i", $var);
                                });

                                if ($matches) { // se ha encontrado el termino
                                    $newNews = $directorio . $this->notas->id . ".json";
                                    $json = json_encode($this->notas, JSON_UNESCAPED_UNICODE);
                                    file_put_contents($newNews, $json);
                                    echo $newNews . "\n";

                                    $dato = $this->notas->id . "|" . $this->notas->estado . "|" . $this->notas->municipio . "|" . $this->notas->asentamiento . "|" . $this->notas->cp . "|" . $this->notas->idPostalCode . "|" . $this->notas->copo . "|jalisco\n";

                                    $fh = fopen($directorio . "notaTaggeada.txt", "a+") or die("Se produjo un error al crear el archivo");
                                    fwrite($fh, $dato) or die("No se pudo escribir en el archivo");
                                    fclose($fh);
                                    break;
                                } else { // El termino no se ha encontrado
                                }
                            }

                            $dato = $news->id ."\n";

                            $fh = fopen($directorio . "no-NotaTaggeada.txt", "a+") or die("Se produjo un error al crear el archivo");
                            fwrite($fh, $dato) or die("No se pudo escribir en el archivo");
                            fclose($fh);
                        }
                    }
                }
            }

            unset($news);
            unset($json_estado);

            return true;
        } catch (Exception $ex) {
            \Log::error($ex);
            throw new Exception($ex->getMessage());
        }
    }

    private function NotasYaTaggeadas($news)
    {
        try {
            foreach ($this->notasTaggeadas as $item) {
                if ($item == $news->id) {
                    echo $item . " == " . $news->id . "\n";
                    return true;
                } else echo $item . " !== " . $news->id . "\n";
            }
            return false;
        } catch (Exception $ex) {
            \Log::error($ex->getMessage());
            throw new Exception($ex->getMessage());
        }
    }

    private function CagarEstadosMemoria()
    {
        try {
            ini_set('memory_limit', '-1');

            $thefolder = "public/estados/";
            if ($handler = opendir($thefolder)) {

                while (false !== ($archivo = readdir($handler))) {

                    if ($archivo != "." && $archivo != "..") {

                        $datos_estado = file_get_contents("public/estados/" . $archivo);
                        $json_estado = json_decode($datos_estado, true);

                        array_push($this->cacheEstados, $json_estado[0]);

                        echo "Se cargo el archivo " . $archivo . " a memoria\n";

                        unset($datos_estado);
                        unset($json_estado);
                    }
                }
                closedir($handler);
            }
        } catch (Exception $ex) {
            \Log::error($ex->getMessage());
            throw new Exception($ex->getMessage());
        }
    }

    private function ActualizarNoticiaTaggeada()
    {
        try {
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

                        echo $url . "\n";

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
        } catch (Exception $ex) {
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
