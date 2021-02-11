<?php

namespace App\Jobs;

use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class TaggeoNoticiasAsentamiento implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $cacheEstados = array();
    private $notasTaggeadas = array();
    private $splitEstado = array();
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
            echo "===== Cargar Memoria Datos ====== \n";
            $this->CagarEstadosMemoria();
            echo "===== Iniciar Proceso de Taggeo ====== \n";
            $this->IniciarProceso();
        } catch (Exception $ex) {
            \Log::error($ex->getMessage());
            throw new Exception($ex->getMessage());
        }
    }

    public function IniciarProceso()
    {
        try {
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
            $count = 1;

            echo "Total de notas a taggear " . count($json_news) . "\n";

            foreach ($json_news as $news) {
                echo $count++ . " .- " . $news->id . "-noticia ***** " . strtolower($news->title) . " ***** para ser taggeada.\n";
                $this->TaggeoNews($news);
            }

            unset($datos_news);
            unset($json_news);
            unset($json_estado);
            unset($this->cacheEstados);
        } catch (Exception $ex) {
            \Log::error($ex->getMessage());
            throw new Exception($ex->getMessage());
        }
    }

    private function TaggeoNews($news)
    {
        try {
            ini_set('memory_limit', '-1');

            $directorio = "public/taggeo/" . date("Y") . "/" . date("m") . "/" . date("d") . "/";
            $splitEstado = array();
            $count = 1;
            $dondeTaggeo = "";

            $json_estado = json_decode(json_encode($this->cacheEstados));

            foreach ($json_estado as $estado) {

                foreach ($estado as $item) {
                    $splitEstado = explode("|", $item->url);

                    $array_contenido = explode(" ", strtolower($news->title)); // BUSCAR EN TITLE

                    $a = $splitEstado[0]; // ESTADO
                    $matches = array_filter($array_contenido, function ($var) use ($a) {
                        return stristr($var, $a);
                        //return preg_match("/$a/i", $var);
                    });

                    if ($matches) {

                        $a = $splitEstado[1]; // MUNICIPIO
                        $matches = array_filter($array_contenido, function ($var) use ($a) {
                            return stristr($var, $a);
                            //return preg_match("/$a/i", $var);
                        });

                        if ($matches) {

                            $a = $splitEstado[2]; // ASENTAMIENTO
                            $matches = array_filter($array_contenido, function ($var) use ($a) {
                                return stristr($var, $a);
                                //return preg_match("/$a/i", $var);
                            });

                            if ($matches) {
                            }
                        }
                    } else { // El termino no se ha encontrado

                        $a = $splitEstado[1]; // MUNICIPIO

                        $matches = array_filter($array_contenido, function ($var) use ($a) {
                            return stristr($var, $a);
                            //return preg_match("/$a/i", $var);
                        });

                        if ($matches) { // se ha encontrado el termino

                            $a = $splitEstado[2]; // ASENTAMIENTO
                            $matches = array_filter($array_contenido, function ($var) use ($a) {
                                return stristr($var, $a);
                                //return preg_match("/$a/i", $var);
                            });

                            if ($matches) {
                            }
                        } else {

                            $a = $splitEstado[2]; // ASENTAMIENTO

                            $matches = array_filter($array_contenido, function ($var) use ($a) {
                                return stristr($var, $a);
                                //return preg_match("/$a/i", $var);
                            });

                            if ($matches) { // se ha encontrado el termino

                                $newNews = $directorio . $news->id . ".json";
                                $dato = $news->id . "|" . $splitEstado[0] . "|" . $splitEstado[1] . "|" . $splitEstado[2] . "|" . $splitEstado[3] . "\n";
                                $json = json_encode($dato, JSON_UNESCAPED_UNICODE);
                                file_put_contents($newNews, $json);

                                echo "------" . $count++ . " .-  " . $newNews . " => " . $dato;

                                $fh = fopen($directorio . "notaTaggeada.txt", "a+") or die("Se produjo un error al crear el archivo");
                                fwrite($fh, $dato) or die("No se pudo escribir en el archivo");
                                fclose($fh);
                                break;
                            } else {

                                $array_contenido = explode(" ", strtolower($news->summary)); // BUSCAR POR SUMMARY

                                $a = $splitEstado[0]; // ESTADO
                                $matches = array_filter($array_contenido, function ($var) use ($a) {
                                    return stristr($var, $a);
                                    //return preg_match("/$a/i", $var);
                                });

                                if ($matches) { // se ha encontrado el termino

                                    $a = $splitEstado[1]; // MUNICIPIO
                                    $matches = array_filter($array_contenido, function ($var) use ($a) {
                                        return stristr($var, $a);
                                        //return preg_match("/$a/i", $var);
                                    });

                                    if ($matches) {

                                        $a = $splitEstado[2]; // ASENTAMIENTO
                                        $matches = array_filter($array_contenido, function ($var) use ($a) {
                                            return stristr($var, $a);
                                            //return preg_match("/$a/i", $var);
                                        });

                                        if ($matches) {
                                        }
                                    }
                                } else {
                                    $a = $splitEstado[1]; // MUNICIPIO
                                    $matches = array_filter($array_contenido, function ($var) use ($a) {
                                        return stristr($var, $a);
                                        //return preg_match("/$a/i", $var);
                                    });

                                    if ($matches) { // se ha encontrado el termino

                                        $a = $splitEstado[2]; // ASENTAMIENTO
                                        $matches = array_filter($array_contenido, function ($var) use ($a) {
                                            return stristr($var, $a);
                                            //return preg_match("/$a/i", $var);
                                        });

                                        if ($matches) {
                                        }
                                    } else {
                                        $a = $splitEstado[2]; // ASENTAMIENTO
                                        $matches = array_filter($array_contenido, function ($var) use ($a) {
                                            return stristr($var, $a);
                                            //return preg_match("/$a/i", $var);
                                        });

                                        if ($matches) { // se ha encontrado el termino
                                            $newNews = $directorio . $news->id . ".json";
                                            $dato = $news->id . "|" . $splitEstado[0] . "|" . $splitEstado[1] . "|" . $splitEstado[2] . "|" . $splitEstado[3] . "\n";
                                            $json = json_encode($dato, JSON_UNESCAPED_UNICODE);
                                            file_put_contents($newNews, $json);

                                            echo "------" . $count++ . " .-  " . $newNews . "\n";

                                            $fh = fopen($directorio . "notaTaggeada.txt", "a+") or die("Se produjo un error al crear el archivo");
                                            fwrite($fh, $dato) or die("No se pudo escribir en el archivo");
                                            fclose($fh);
                                            break;
                                        } else {

                                            if ($news->content != "") {

                                                $array_contenido = explode(" ", strtolower($news->content)); // BUSCAR EN CONTENIDO

                                                $a = $splitEstado[0]; // ESTADO
                                                $matches = array_filter($array_contenido, function ($var) use ($a) {
                                                    return stristr($var, $a);
                                                    //return preg_match("/$a/i", $var);
                                                });

                                                if ($matches) { // se ha encontrado el termino

                                                    $a = $splitEstado[1]; // MUNICIPIO
                                                    $matches = array_filter($array_contenido, function ($var) use ($a) {
                                                        return stristr($var, $a);
                                                        //return preg_match("/$a/i", $var);
                                                    });

                                                    if ($matches) {

                                                        $a = $splitEstado[2]; // ASENTAMIENTO
                                                        $matches = array_filter($array_contenido, function ($var) use ($a) {
                                                            return stristr($var, $a);
                                                            //return preg_match("/$a/i", $var);
                                                        });

                                                        if ($matches) {
                                                        }
                                                    }
                                                } else {
                                                    $a = $splitEstado[1]; // MUNICIPIO
                                                    $matches = array_filter($array_contenido, function ($var) use ($a) {
                                                        return stristr($var, $a);
                                                        //return preg_match("/$a/i", $var);
                                                    });

                                                    if ($matches) { // se ha encontrado el termino

                                                        $a = $splitEstado[2]; // ASENTAMIENTO
                                                        $matches = array_filter($array_contenido, function ($var) use ($a) {
                                                            return stristr($var, $a);
                                                            //return preg_match("/$a/i", $var);
                                                        });

                                                        if ($matches) {
                                                        }
                                                    } else {
                                                        $a = $splitEstado[2]; // ASENTAMIENTO
                                                        $matches = array_filter($array_contenido, function ($var) use ($a) {
                                                            return stristr($var, $a);
                                                            //return preg_match("/$a/i", $var);
                                                        });

                                                        if ($matches) { // se ha encontrado el termino
                                                            $newNews = $directorio . $news->id . ".json";
                                                            $dato = $news->id . "|" . $splitEstado[0] . "|" . $splitEstado[1] . "|" . $splitEstado[2] . "|" . $splitEstado[3] . "\n";
                                                            $json = json_encode($dato, JSON_UNESCAPED_UNICODE);
                                                            file_put_contents($newNews, $json);

                                                            echo "------" . $count++ . " .-  " . $newNews . " => " . $dato;

                                                            $fh = fopen($directorio . "notaTaggeada.txt", "a+") or die("Se produjo un error al crear el archivo");
                                                            fwrite($fh, $dato) or die("No se pudo escribir en el archivo");
                                                            fclose($fh);
                                                            break;
                                                        } else {
                                                            $dato = $news->id . "\n";

                                                            $fh = fopen($directorio . "no-NotaTaggeada.txt", "a+") or die("Se produjo un error al crear el archivo");
                                                            fwrite($fh, $dato) or die("No se pudo escribir en el archivo");
                                                            fclose($fh);
                                                            break;
                                                        }
                                                    }
                                                }
                                            } else {
                                                $dato = $news->id . "\n";

                                                $fh = fopen($directorio . "no-NotaTaggeada.txt", "a+") or die("Se produjo un error al crear el archivo");
                                                fwrite($fh, $dato) or die("No se pudo escribir en el archivo");
                                                fclose($fh);
                                                break;
                                            }
                                        }
                                    }
                                }
                            }
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
                        $json_estado = $json_estado[0];

                        array_push($this->cacheEstados, $json_estado);

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
