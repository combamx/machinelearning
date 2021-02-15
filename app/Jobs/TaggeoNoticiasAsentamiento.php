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
    private $splitEstado = array();
    private $notasTaggeadas = array();

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

            if (file_exists($directorio . "ninguno.txt")) unlink($directorio . "ninguno.txt");
            if (file_exists($directorio . "taggeo-estado.txt")) unlink($directorio . "taggeo-estado.txt");
            if (file_exists($directorio . "taggeo-municipio.txt")) unlink($directorio . "taggeo-municipio.txt");
            if (file_exists($directorio . "taggeo-asentamiento.txt")) unlink($directorio . "taggeo-asentamiento.txt");
            if (file_exists($directorio . "NotaNoTaggeada.txt")) unlink($directorio . "NotaNoTaggeada.txt");

            $datos_news = file_get_contents($news);
            $json_news = json_decode($datos_news);
            $count = 1;

            echo "Total de notas a taggear " . count($json_news) . "\n";

            foreach ($json_news as $news) {
                echo $count++ . " .- " . $news->id . " - " . strtolower($news->title) . "\n";
                //$this->TaggeoNews($news);
                $this->TaggeoNewsContenido($news);
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

    private function TaggeoNewsTituloResenaContenido($news)
    {
        try {
            ini_set('memory_limit', '-1');

            $directorio = "public/taggeo/" . date("Y") . "/" . date("m") . "/" . date("d") . "/";
            $splitEstado = array();
            $json_estado = json_decode(json_encode($this->cacheEstados));

            foreach ($json_estado as $estado) {
                foreach ($estado as $item) {
                    $dondeTaggeo = "";
                    $puntaje = 0;
                    $splitEstado = explode("|", $item->url);

                    //*********** ESTADO TITULO ***********************/
                    $a = $splitEstado[0]; // ESTADO
                    $array_contenido = explode(" ", strtolower($news->title)); // BUSCAR EN TITLE

                    $matches = array_filter($array_contenido, function ($var) use ($a) {
                        //return stristr($var, $a);
                        return preg_match("/$a/i", $var);
                    });

                    if ($matches == 1) {
                        $puntaje = 1;
                        $dondeTaggeo =  $news->id . "|" . $splitEstado[0] . "|";

                        //*********** MUNICIPIO TITULO ***********************/
                        $a = $splitEstado[1];
                        $matches = array_filter($array_contenido, function ($var) use ($a) {
                            return stristr($var, $a);
                            //return preg_match("/$a/i", $var);
                        });

                        if ($matches == 1) {
                            $puntaje = 2;
                            $dondeTaggeo =  $news->id . "|" . $splitEstado[0] . "|" . $splitEstado[1] . "|";

                            //*********** ASENTAMIENTO TITULO ***********************/
                            $a = $splitEstado[2];
                            $matches = array_filter($array_contenido, function ($var) use ($a) {
                                //return stristr($var, $a);
                                return preg_match("/$a/i", $var);
                            });

                            if ($matches == 1) {
                                $puntaje = 3;
                                $dondeTaggeo =  $news->id . "|" . $splitEstado[0] . "|" . $splitEstado[1] . "|" . $splitEstado[2] . "|";
                            }
                            //*********** ASENTAMIENTO TITULO ***********************/
                        }
                        //*********** MUNICIPIO TITULO ***********************/

                        //echo $dondeTaggeo . " ==> " . $puntaje . " ESTADO TITULO\n";
                        $archivoTaggeo = "ninguno.txt";
                        switch ($puntaje) {
                            case 1:
                                $archivoTaggeo = "taggeo-estado.txt";
                                break;
                            case 2:
                                $archivoTaggeo = "taggeo-municipio.txt";
                                break;
                            case 3:
                                $archivoTaggeo = "taggeo-asentamiento.txt";
                                break;
                        }
                        $fh = fopen($directorio . $archivoTaggeo, "a+") or die("Se produjo un error al crear el archivo");
                        fwrite($fh, ($dondeTaggeo . "\n")) or die("No se pudo escribir en el archivo");
                        fclose($fh);
                        break;
                    }
                    //*********** ESTADO TITULO ***********************/
                    else {
                        //*********** MUNICIPIO TITULO ***********************/
                        $a = $splitEstado[1];
                        $matches = array_filter($array_contenido, function ($var) use ($a) {
                            //return stristr($var, $a);
                            return preg_match("/$a/i", $var);
                        });

                        if ($matches == 1) {
                            $puntaje = 2;
                            $dondeTaggeo =  $news->id . "|" . $splitEstado[0] . "|" . $splitEstado[1] . "|";

                            //*********** ASENTAMIENTO TITULO ***********************/
                            $a = $splitEstado[2];
                            $matches = array_filter($array_contenido, function ($var) use ($a) {
                                //return stristr($var, $a);
                                return preg_match("/$a/i", $var);
                            });

                            if ($matches == 1) {
                                $puntaje = 3;
                                $dondeTaggeo =  $news->id . "|" . $splitEstado[0] . "|" . $splitEstado[1] . "|" . $splitEstado[2] . "|";
                            }
                            //*********** ASENTAMIENTO TITULO ***********************/
                            //echo $dondeTaggeo . " ==> " . $puntaje . " MUNICIPIO TITULO\n";
                            $archivoTaggeo = "ninguno.txt";
                            switch ($puntaje) {
                                case 1:
                                    $archivoTaggeo = "taggeo-estado.txt";
                                    break;
                                case 2:
                                    $archivoTaggeo = "taggeo-municipio.txt";
                                    break;
                                case 3:
                                    $archivoTaggeo = "taggeo-asentamiento.txt";
                                    break;
                            }
                            $fh = fopen($directorio . $archivoTaggeo, "a+") or die("Se produjo un error al crear el archivo");
                            fwrite($fh, ($dondeTaggeo . "\n")) or die("No se pudo escribir en el archivo");
                            fclose($fh);
                            break;
                        }
                        //*********** MUNICIPIO TITULO ***********************/
                        else {

                            //*********** ASENTAMIENTO TITULO ***********************/
                            $a = $splitEstado[2];
                            $matches = array_filter($array_contenido, function ($var) use ($a) {
                                //return stristr($var, $a);
                                return preg_match("/$a/i", $var);
                            });

                            if ($matches == 1) {
                                $puntaje = 3;
                                $dondeTaggeo =  $news->id . "|" . $splitEstado[0] . "|" . $splitEstado[2] . "|" . $splitEstado[2];
                                //echo $dondeTaggeo . " ==> " . $puntaje . " ASENTAMIENTO TITULO\n";
                                $archivoTaggeo = "ninguno.txt";
                                switch ($puntaje) {
                                    case 1:
                                        $archivoTaggeo = "taggeo-estado.txt";
                                        break;
                                    case 2:
                                        $archivoTaggeo = "taggeo-municipio.txt";
                                        break;
                                    case 3:
                                        $archivoTaggeo = "taggeo-asentamiento.txt";
                                        break;
                                }
                                $fh = fopen($directorio . $archivoTaggeo, "a+") or die("Se produjo un error al crear el archivo");
                                fwrite($fh, ($dondeTaggeo . "\n")) or die("No se pudo escribir en el archivo");
                                fclose($fh);
                                break;
                            }
                            //*********** ASENTAMIENTO TITULO ***********************/

                            else {

                                //*********** ESTADO SUMMARY ***********************/
                                $array_contenido = explode(" ", strtolower($news->summary));
                                $a = $splitEstado[0];
                                $matches = array_filter($array_contenido, function ($var) use ($a) {
                                    //return stristr($var, $a);
                                    return preg_match("/$a/i", $var);
                                });

                                if ($matches == 1) {
                                    $puntaje = 1;
                                    $dondeTaggeo =  $news->id . "|" . $splitEstado[0] . "|";

                                    //*********** MUNICIPIO SUMMARY ***********************/
                                    $a = $splitEstado[1]; // MUNICIPIO
                                    $matches = array_filter($array_contenido, function ($var) use ($a) {
                                        //return stristr($var, $a);
                                        return preg_match("/$a/i", $var);
                                    });

                                    if ($matches == 1) {
                                        $puntaje = 2;
                                        $dondeTaggeo =  $news->id . "|" . $splitEstado[0] . "|" . $splitEstado[1] . "|";

                                        //*********** ASENTAMIENTO SUMMARY ***********************/
                                        $a = $splitEstado[2];
                                        $matches = array_filter($array_contenido, function ($var) use ($a) {
                                            //return stristr($var, $a);
                                            return preg_match("/$a/i", $var);
                                        });

                                        if ($matches == 1) {
                                            $puntaje = 3;
                                            $dondeTaggeo =  $news->id . "|" . $splitEstado[0] . "|" . $splitEstado[1] . "|" . $splitEstado[2] . "|";
                                        }
                                        //*********** ASENTAMIENTO SUMMARY ***********************/
                                    }
                                    //*********** MUNICIPIO SUMMARY ***********************/

                                    //echo $dondeTaggeo . " ==> " . $puntaje . " ESTADO SUMMARY\n";
                                    $archivoTaggeo = "ninguno.txt";
                                    switch ($puntaje) {
                                        case 1:
                                            $archivoTaggeo = "taggeo-estado.txt";
                                            break;
                                        case 2:
                                            $archivoTaggeo = "taggeo-municipio.txt";
                                            break;
                                        case 3:
                                            $archivoTaggeo = "taggeo-asentamiento.txt";
                                            break;
                                    }
                                    $fh = fopen($directorio . $archivoTaggeo, "a+") or die("Se produjo un error al crear el archivo");
                                    fwrite($fh, ($dondeTaggeo . "\n")) or die("No se pudo escribir en el archivo");
                                    fclose($fh);
                                    break;
                                }
                                //*********** ESTADO SUMMARY ***********************/

                                else {

                                    //*********** MUNICIPIO SUMMARY ***********************/
                                    $a = $splitEstado[1]; // MUNICIPIO
                                    $matches = array_filter($array_contenido, function ($var) use ($a) {
                                        //return stristr($var, $a);
                                        return preg_match("/$a/i", $var);
                                    });

                                    if ($matches == 1) { // se ha encontrado el termino

                                        $puntaje = 2;
                                        $dondeTaggeo =  $news->id . "|" . $splitEstado[0] . "|" . $splitEstado[1] . "|";

                                        //*********** ASENTAMIENTO SUMMARY ***********************/
                                        $a = $splitEstado[2];
                                        $matches = array_filter($array_contenido, function ($var) use ($a) {
                                            //return stristr($var, $a);
                                            return preg_match("/$a/i", $var);
                                        });

                                        if ($matches == 1) {
                                            $puntaje = 3;
                                            $dondeTaggeo =  $news->id . "|" . $splitEstado[0] . "|" . $splitEstado[1] . "|" . $splitEstado[2] . "|";
                                        }
                                        //*********** ASENTAMIENTO SUMMARY ***********************/

                                        //echo $dondeTaggeo . " ==> " . $puntaje . " MUNICIPIO SUMMARY\n";
                                        $archivoTaggeo = "ninguno.txt";
                                        switch ($puntaje) {
                                            case 1:
                                                $archivoTaggeo = "taggeo-estado.txt";
                                                break;
                                            case 2:
                                                $archivoTaggeo = "taggeo-municipio.txt";
                                                break;
                                            case 3:
                                                $archivoTaggeo = "taggeo-asentamiento.txt";
                                                break;
                                        }
                                        $fh = fopen($directorio . $archivoTaggeo, "a+") or die("Se produjo un error al crear el archivo");
                                        fwrite($fh, ($dondeTaggeo . "\n")) or die("No se pudo escribir en el archivo");
                                        fclose($fh);
                                        break;
                                    }
                                    //*********** MUNICIPIO SUMMARY ***********************/

                                    else {

                                        //*********** ASENTAMIENTO SUMMARY ***********************/
                                        $a = $splitEstado[2];
                                        $matches = array_filter($array_contenido, function ($var) use ($a) {
                                            //return stristr($var, $a);
                                            return preg_match("/$a/i", $var);
                                        });

                                        if ($matches == 1) {
                                            $puntaje = 3;
                                            $dondeTaggeo =  $news->id . "|" . $splitEstado[0] . "|" . $splitEstado[1] . "|" . $splitEstado[2] . "|";
                                            //echo $dondeTaggeo . " ==> " . $puntaje . " ASENTAMIENTO SUMMARY\n";
                                            $archivoTaggeo = "ninguno.txt";
                                            switch ($puntaje) {
                                                case 1:
                                                    $archivoTaggeo = "taggeo-estado.txt";
                                                    break;
                                                case 2:
                                                    $archivoTaggeo = "taggeo-municipio.txt";
                                                    break;
                                                case 3:
                                                    $archivoTaggeo = "taggeo-asentamiento.txt";
                                                    break;
                                            }
                                            $fh = fopen($directorio . $archivoTaggeo, "a+") or die("Se produjo un error al crear el archivo");
                                            fwrite($fh, ($dondeTaggeo . "\n")) or die("No se pudo escribir en el archivo");
                                            fclose($fh);
                                            break;
                                        }
                                        //*********** ASENTAMIENTO SUMMARY ***********************/

                                        else {

                                            if ($news->content != "") {

                                                $array_contenido = explode(" ", strtolower($news->content)); // BUSCAR EN CONTENIDO

                                                //*********** ESTADO CONTENIDO ***********************/
                                                $a = $splitEstado[0];
                                                $matches = array_filter($array_contenido, function ($var) use ($a) {
                                                    //return stristr($var, $a);
                                                    return preg_match("/$a/i", $var);
                                                });

                                                if ($matches == 1) {
                                                    $puntaje = 1;
                                                    $dondeTaggeo =  $news->id . "|" . $splitEstado[0] . "|";

                                                    //*********** MUNICIPIO CONTENIDO ***********************/
                                                    $a = $splitEstado[1]; // MUNICIPIO
                                                    $matches = array_filter($array_contenido, function ($var) use ($a) {
                                                        //return stristr($var, $a);
                                                        return preg_match("/$a/i", $var);
                                                    });

                                                    if ($matches == 1) {
                                                        $puntaje = 2;
                                                        $dondeTaggeo =  $news->id . "|" . $splitEstado[0] . "|" . $splitEstado[1] . "|";

                                                        //*********** ASENTAMIENTO CONTENIDO ***********************/
                                                        $a = $splitEstado[2]; // ASENTAMIENTO
                                                        $matches = array_filter($array_contenido, function ($var) use ($a) {
                                                            //return stristr($var, $a);
                                                            return preg_match("/$a/i", $var);
                                                        });

                                                        if ($matches == 1) {
                                                            $puntaje = 3;
                                                            $dondeTaggeo =  $news->id . "|" . $splitEstado[0] . "|" . $splitEstado[1] . "|" . $splitEstado[2] . "|";
                                                        }
                                                        //*********** ASENTAMIENTO CONTENIDO ***********************/
                                                    }
                                                    //*********** MINICIPIO CONTENIDO ***********************/

                                                    //echo $dondeTaggeo . " ==> " . $puntaje . " ESTADO CONTENIDO\n";
                                                    $archivoTaggeo = "ninguno.txt";
                                                    switch ($puntaje) {
                                                        case 1:
                                                            $archivoTaggeo = "taggeo-estado.txt";
                                                            break;
                                                        case 2:
                                                            $archivoTaggeo = "taggeo-municipio.txt";
                                                            break;
                                                        case 3:
                                                            $archivoTaggeo = "taggeo-asentamiento.txt";
                                                            break;
                                                    }
                                                    $fh = fopen($directorio . $archivoTaggeo, "a+") or die("Se produjo un error al crear el archivo");
                                                    fwrite($fh, ($dondeTaggeo . "\n")) or die("No se pudo escribir en el archivo");
                                                    fclose($fh);
                                                    break;
                                                }
                                                //*********** ESTADO CONTENIDO ***********************/

                                                else {
                                                    //*********** MUNICIPIO CONTENIDO ***********************/
                                                    $a = $splitEstado[1];
                                                    $matches = array_filter($array_contenido, function ($var) use ($a) {
                                                        //return stristr($var, $a);
                                                        return preg_match("/$a/i", $var);
                                                    });

                                                    if ($matches == 1) {
                                                        $puntaje = 2;
                                                        $dondeTaggeo =  $news->id . "|" . $splitEstado[0] . "|" . $splitEstado[1] . "|";

                                                        //*********** ASENTAMIENTO CONTENIDO ***********************/
                                                        $a = $splitEstado[2]; // ASENTAMIENTO
                                                        $matches = array_filter($array_contenido, function ($var) use ($a) {
                                                            //return stristr($var, $a);
                                                            return preg_match("/$a/i", $var);
                                                        });

                                                        if ($matches == 1) {
                                                            $puntaje = 3;
                                                            $dondeTaggeo =  $news->id . "|" . $splitEstado[0] . "|" . $splitEstado[1] . "|" . $splitEstado[2];
                                                        }
                                                        //*********** ASENTAMIENTO CONTENIDO ***********************/

                                                        //echo $dondeTaggeo . " ==> " . $puntaje . " MUNICIPIO CONTENIDO\n";
                                                        $archivoTaggeo = "ninguno.txt";
                                                        switch ($puntaje) {
                                                            case 1:
                                                                $archivoTaggeo = "taggeo-estado.txt";
                                                                break;
                                                            case 2:
                                                                $archivoTaggeo = "taggeo-municipio.txt";
                                                                break;
                                                            case 3:
                                                                $archivoTaggeo = "taggeo-asentamiento.txt";
                                                                break;
                                                        }
                                                        $fh = fopen($directorio . $archivoTaggeo, "a+") or die("Se produjo un error al crear el archivo");
                                                        fwrite($fh, ($dondeTaggeo . "\n")) or die("No se pudo escribir en el archivo");
                                                        fclose($fh);
                                                        break;
                                                    }
                                                    //*********** MUNICIPIO CONTENIDO ***********************/

                                                    else {
                                                        //*********** ASENTAMIENTO CONTENIDO ***********************/
                                                        $a = $splitEstado[2];
                                                        $matches = array_filter($array_contenido, function ($var) use ($a) {
                                                            //return stristr($var, $a);
                                                            return preg_match("/$a/i", $var);
                                                        });

                                                        if ($matches == 1) {
                                                            $puntaje = 3;
                                                            $dondeTaggeo =  $news->id . "|" . $splitEstado[0] . "|" . $splitEstado[1] . "|" . $splitEstado[2] . "|";
                                                            //echo $dondeTaggeo . " ==> " . $puntaje . " ASENTAMIENTO CONTENIDO\n";
                                                            $archivoTaggeo = "ninguno.txt";
                                                            switch ($puntaje) {
                                                                case 1:
                                                                    $archivoTaggeo = "taggeo-estado.txt";
                                                                    break;
                                                                case 2:
                                                                    $archivoTaggeo = "taggeo-municipio.txt";
                                                                    break;
                                                                case 3:
                                                                    $archivoTaggeo = "taggeo-asentamiento.txt";
                                                                    break;
                                                            }
                                                            $fh = fopen($directorio . $archivoTaggeo, "a+") or die("Se produjo un error al crear el archivo");
                                                            fwrite($fh, ($dondeTaggeo . "\n")) or die("No se pudo escribir en el archivo");
                                                            fclose($fh);
                                                            break;
                                                        }
                                                        //*********** ASENTAMIENTO CONTENIDO ***********************/
                                                    }
                                                }
                                            } else {
                                                $fh = fopen($directorio . "NotaNoTaggeada.txt", "a+") or die("Se produjo un error al crear el archivo");
                                                fwrite($fh, ($dondeTaggeo . "\n")) or die("No se pudo escribir en el archivo");
                                                fclose($fh);
                                                break;
                                            }
                                        }
                                    }
                                }
                            }
                        }
                        break;
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

    private function TaggeoNewsContenido($news)
    {
        try {
            ini_set('memory_limit', '-1');

            $directorio = "public/taggeo/" . date("Y") . "/" . date("m") . "/" . date("d") . "/";
            $splitEstado = array();
            $json_estado = json_decode(json_encode($this->cacheEstados));

            foreach ($json_estado as $estado) {
                foreach ($estado as $item) {
                    $dondeTaggeo = "";
                    $puntaje = 0;
                    $splitEstado = explode("|", $item->url);

                    if ($news->content != "") {

                        $array_contenido = explode(" ", strtolower($news->content)); // BUSCAR EN CONTENIDO

                        //*********** ESTADO CONTENIDO ***********************/
                        $a = $splitEstado[0];
                        $matches = array_filter($array_contenido, function ($var) use ($a) {
                            //return stristr($var, $a);
                            return preg_match("/$a/i", $var);
                        });

                        if ($matches == 1) {
                            $puntaje = 1;
                            $dondeTaggeo =  $news->id . "|" . $splitEstado[0] . "|";

                            //*********** MUNICIPIO CONTENIDO ***********************/
                            $a = $splitEstado[1]; // MUNICIPIO
                            $matches = array_filter($array_contenido, function ($var) use ($a) {
                                //return stristr($var, $a);
                                return preg_match("/$a/i", $var);
                            });

                            if ($matches == 1) {
                                $puntaje = 2;
                                $dondeTaggeo =  $news->id . "|" . $splitEstado[0] . "|" . $splitEstado[1] . "|";

                                //*********** ASENTAMIENTO CONTENIDO ***********************/
                                $a = $splitEstado[2]; // ASENTAMIENTO
                                $matches = array_filter($array_contenido, function ($var) use ($a) {
                                    //return stristr($var, $a);
                                    return preg_match("/$a/i", $var);
                                });

                                if ($matches == 1) {
                                    $puntaje = 3;
                                    $dondeTaggeo =  $news->id . "|" . $splitEstado[0] . "|" . $splitEstado[1] . "|" . $splitEstado[2] . "|";
                                }
                                //*********** ASENTAMIENTO CONTENIDO ***********************/
                            }
                            //*********** MINICIPIO CONTENIDO ***********************/

                            //echo $dondeTaggeo . " ==> " . $puntaje . " ESTADO CONTENIDO\n";
                            $archivoTaggeo = "ninguno.txt";
                            switch ($puntaje) {
                                case 1:
                                    $archivoTaggeo = "taggeo-estado.txt";
                                    break;
                                case 2:
                                    $archivoTaggeo = "taggeo-municipio.txt";
                                    break;
                                case 3:
                                    $archivoTaggeo = "taggeo-asentamiento.txt";
                                    break;
                            }
                            $s = 0;
                            foreach ($this->notasTaggeadas as $t) {
                                if ($t == $news->id) {
                                    $s++;
                                    break;
                                }
                            }
                            if ($s == 0) {
                                $fh = fopen($directorio . $archivoTaggeo, "a+") or die("Se produjo un error al crear el archivo");
                                fwrite($fh, ($dondeTaggeo . "\n")) or die("No se pudo escribir en el archivo");
                                fclose($fh);
                                array_push($this->notasTaggeadas, $news->id);
                            }

                            //\Log::debug($this->notasTaggeadas);
                            break;
                        }
                        //*********** ESTADO CONTENIDO ***********************/

                        else {
                            //*********** MUNICIPIO CONTENIDO ***********************/
                            $a = $splitEstado[1];
                            $matches = array_filter($array_contenido, function ($var) use ($a) {
                                //return stristr($var, $a);
                                return preg_match("/$a/i", $var);
                            });

                            if ($matches == 1) {
                                $puntaje = 2;
                                $dondeTaggeo =  $news->id . "|" . $splitEstado[0] . "|" . $splitEstado[1] . "|";

                                //*********** ASENTAMIENTO CONTENIDO ***********************/
                                $a = $splitEstado[2]; // ASENTAMIENTO
                                $matches = array_filter($array_contenido, function ($var) use ($a) {
                                    //return stristr($var, $a);
                                    return preg_match("/$a/i", $var);
                                });

                                if ($matches == 1) {
                                    $puntaje = 3;
                                    $dondeTaggeo =  $news->id . "|" . $splitEstado[0] . "|" . $splitEstado[1] . "|" . $splitEstado[2];
                                }
                                //*********** ASENTAMIENTO CONTENIDO ***********************/

                                //echo $dondeTaggeo . " ==> " . $puntaje . " MUNICIPIO CONTENIDO\n";
                                $archivoTaggeo = "ninguno.txt";
                                switch ($puntaje) {
                                    case 1:
                                        $archivoTaggeo = "taggeo-estado.txt";
                                        break;
                                    case 2:
                                        $archivoTaggeo = "taggeo-municipio.txt";
                                        break;
                                    case 3:
                                        $archivoTaggeo = "taggeo-asentamiento.txt";
                                        break;
                                }
                                $s = 0;
                                foreach ($this->notasTaggeadas as $t) {
                                    if ($t == $news->id) {
                                        $s++;
                                        break;
                                    }
                                }
                                if ($s == 0) {
                                    $fh = fopen($directorio . $archivoTaggeo, "a+") or die("Se produjo un error al crear el archivo");
                                    fwrite($fh, ($dondeTaggeo . "\n")) or die("No se pudo escribir en el archivo");
                                    fclose($fh);
                                    array_push($this->notasTaggeadas, $news->id);
                                }
                                //\Log::debug($this->notasTaggeadas);
                                break;
                            }
                            //*********** MUNICIPIO CONTENIDO ***********************/

                            else {
                                //*********** ASENTAMIENTO CONTENIDO ***********************/
                                $a = $splitEstado[2];
                                $matches = array_filter($array_contenido, function ($var) use ($a) {
                                    //return stristr($var, $a);
                                    return preg_match("/$a/i", $var);
                                });

                                if ($matches == 1) {
                                    $puntaje = 3;
                                    $dondeTaggeo =  $news->id . "|" . $splitEstado[0] . "|" . $splitEstado[1] . "|" . $splitEstado[2] . "|";
                                    //echo $dondeTaggeo . " ==> " . $puntaje . " ASENTAMIENTO CONTENIDO\n";
                                    $archivoTaggeo = "ninguno.txt";
                                    switch ($puntaje) {
                                        case 1:
                                            $archivoTaggeo = "taggeo-estado.txt";
                                            break;
                                        case 2:
                                            $archivoTaggeo = "taggeo-municipio.txt";
                                            break;
                                        case 3:
                                            $archivoTaggeo = "taggeo-asentamiento.txt";
                                            break;
                                    }
                                    $s = 0;
                                    foreach ($this->notasTaggeadas as $t) {
                                        if ($t == $news->id) {
                                            $s++;
                                            break;
                                        }
                                    }
                                    if ($s == 0) {
                                        $fh = fopen($directorio . $archivoTaggeo, "a+") or die("Se produjo un error al crear el archivo");
                                        fwrite($fh, ($dondeTaggeo . "\n")) or die("No se pudo escribir en el archivo");
                                        fclose($fh);
                                        array_push($this->notasTaggeadas, $news->id);
                                    }
                                    //\Log::debug($this->notasTaggeadas);
                                    break;
                                }
                                //*********** ASENTAMIENTO CONTENIDO ***********************/
                                else {

                                    //*********** ESTADO SUMMARY ***********************/
                                    $array_contenido = explode(" ", strtolower($news->summary));
                                    $a = $splitEstado[0];
                                    $matches = array_filter($array_contenido, function ($var) use ($a) {
                                        //return stristr($var, $a);
                                        return preg_match("/$a/i", $var);
                                    });

                                    if ($matches == 1) {
                                        $puntaje = 1;
                                        $dondeTaggeo =  $news->id . "|" . $splitEstado[0] . "|";

                                        //*********** MUNICIPIO SUMMARY ***********************/
                                        $a = $splitEstado[1]; // MUNICIPIO
                                        $matches = array_filter($array_contenido, function ($var) use ($a) {
                                            //return stristr($var, $a);
                                            return preg_match("/$a/i", $var);
                                        });

                                        if ($matches == 1) {
                                            $puntaje = 2;
                                            $dondeTaggeo =  $news->id . "|" . $splitEstado[0] . "|" . $splitEstado[1] . "|";

                                            //*********** ASENTAMIENTO SUMMARY ***********************/
                                            $a = $splitEstado[2];
                                            $matches = array_filter($array_contenido, function ($var) use ($a) {
                                                //return stristr($var, $a);
                                                return preg_match("/$a/i", $var);
                                            });

                                            if ($matches == 1) {
                                                $puntaje = 3;
                                                $dondeTaggeo =  $news->id . "|" . $splitEstado[0] . "|" . $splitEstado[1] . "|" . $splitEstado[2] . "|";
                                            }
                                            //*********** ASENTAMIENTO SUMMARY ***********************/
                                        }
                                        //*********** MUNICIPIO SUMMARY ***********************/
                                        $archivoTaggeo = "ninguno.txt";
                                        switch ($puntaje) {
                                            case 1:
                                                $archivoTaggeo = "taggeo-estado.txt";
                                                break;
                                            case 2:
                                                $archivoTaggeo = "taggeo-municipio.txt";
                                                break;
                                            case 3:
                                                $archivoTaggeo = "taggeo-asentamiento.txt";
                                                break;
                                        }
                                        $s = 0;
                                        foreach ($this->notasTaggeadas as $t) {
                                            if ($t == $news->id) {
                                                $s++;
                                                break;
                                            }
                                        }
                                        if ($s == 0) {
                                            $fh = fopen($directorio . $archivoTaggeo, "a+") or die("Se produjo un error al crear el archivo");
                                            fwrite($fh, ($dondeTaggeo . "\n")) or die("No se pudo escribir en el archivo");
                                            fclose($fh);
                                            array_push($this->notasTaggeadas, $news->id);
                                        }
                                        //\Log::debug($this->notasTaggeadas);
                                        break;
                                    }
                                    //*********** ESTADO SUMMARY ***********************/

                                    else {

                                        //*********** MUNICIPIO SUMMARY ***********************/
                                        $a = $splitEstado[1]; // MUNICIPIO
                                        $matches = array_filter($array_contenido, function ($var) use ($a) {
                                            //return stristr($var, $a);
                                            return preg_match("/$a/i", $var);
                                        });

                                        if ($matches == 1) { // se ha encontrado el termino

                                            $puntaje = 2;
                                            $dondeTaggeo =  $news->id . "|" . $splitEstado[0] . "|" . $splitEstado[1] . "|";

                                            //*********** ASENTAMIENTO SUMMARY ***********************/
                                            $a = $splitEstado[2];
                                            $matches = array_filter($array_contenido, function ($var) use ($a) {
                                                //return stristr($var, $a);
                                                return preg_match("/$a/i", $var);
                                            });

                                            if ($matches == 1) {
                                                $puntaje = 3;
                                                $dondeTaggeo =  $news->id . "|" . $splitEstado[0] . "|" . $splitEstado[1] . "|" . $splitEstado[2] . "|";
                                            }
                                            //*********** ASENTAMIENTO SUMMARY ***********************/
                                            $archivoTaggeo = "ninguno.txt";
                                            switch ($puntaje) {
                                                case 1:
                                                    $archivoTaggeo = "taggeo-estado.txt";
                                                    break;
                                                case 2:
                                                    $archivoTaggeo = "taggeo-municipio.txt";
                                                    break;
                                                case 3:
                                                    $archivoTaggeo = "taggeo-asentamiento.txt";
                                                    break;
                                            }
                                            $s = 0;
                                            foreach ($this->notasTaggeadas as $t) {
                                                if ($t == $news->id) {
                                                    $s++;
                                                    break;
                                                }
                                            }
                                            if ($s == 0) {
                                                $fh = fopen($directorio . $archivoTaggeo, "a+") or die("Se produjo un error al crear el archivo");
                                                fwrite($fh, ($dondeTaggeo . "\n")) or die("No se pudo escribir en el archivo");
                                                fclose($fh);
                                                array_push($this->notasTaggeadas, $news->id);
                                            }
                                            //\Log::debug($this->notasTaggeadas);
                                            break;
                                        }
                                        //*********** MUNICIPIO SUMMARY ***********************/

                                        else {

                                            //*********** ASENTAMIENTO SUMMARY ***********************/
                                            $a = $splitEstado[2];
                                            $matches = array_filter($array_contenido, function ($var) use ($a) {
                                                //return stristr($var, $a);
                                                return preg_match("/$a/i", $var);
                                            });

                                            if ($matches == 1) {
                                                $puntaje = 3;
                                                $dondeTaggeo =  $news->id . "|" . $splitEstado[0] . "|" . $splitEstado[1] . "|" . $splitEstado[2] . "|";
                                                $archivoTaggeo = "ninguno.txt";
                                                switch ($puntaje) {
                                                    case 1:
                                                        $archivoTaggeo = "taggeo-estado.txt";
                                                        break;
                                                    case 2:
                                                        $archivoTaggeo = "taggeo-municipio.txt";
                                                        break;
                                                    case 3:
                                                        $archivoTaggeo = "taggeo-asentamiento.txt";
                                                        break;
                                                }
                                                $s = 0;
                                                foreach ($this->notasTaggeadas as $t) {
                                                    if ($t == $news->id) {
                                                        $s++;
                                                        break;
                                                    }
                                                }
                                                if ($s == 0) {
                                                    $fh = fopen($directorio . $archivoTaggeo, "a+") or die("Se produjo un error al crear el archivo");
                                                    fwrite($fh, ($dondeTaggeo . "\n")) or die("No se pudo escribir en el archivo");
                                                    fclose($fh);
                                                    array_push($this->notasTaggeadas, $news->id);
                                                }
                                                //\Log::debug($this->notasTaggeadas);
                                                break;
                                            }
                                            //*********** ASENTAMIENTO SUMMARY ***********************/

                                        }
                                    }
                                }
                            }
                        }
                    } else {

                        //*********** ESTADO SUMMARY ***********************/
                        $array_contenido = explode(" ", strtolower($news->summary));
                        $a = $splitEstado[0];
                        $matches = array_filter($array_contenido, function ($var) use ($a) {
                            //return stristr($var, $a);
                            return preg_match("/$a/i", $var);
                        });

                        if ($matches == 1) {
                            $puntaje = 1;
                            $dondeTaggeo =  $news->id . "|" . $splitEstado[0] . "|";

                            //*********** MUNICIPIO SUMMARY ***********************/
                            $a = $splitEstado[1]; // MUNICIPIO
                            $matches = array_filter($array_contenido, function ($var) use ($a) {
                                //return stristr($var, $a);
                                return preg_match("/$a/i", $var);
                            });

                            if ($matches == 1) {
                                $puntaje = 2;
                                $dondeTaggeo =  $news->id . "|" . $splitEstado[0] . "|" . $splitEstado[1] . "|";

                                //*********** ASENTAMIENTO SUMMARY ***********************/
                                $a = $splitEstado[2];
                                $matches = array_filter($array_contenido, function ($var) use ($a) {
                                    //return stristr($var, $a);
                                    return preg_match("/$a/i", $var);
                                });

                                if ($matches == 1) {
                                    $puntaje = 3;
                                    $dondeTaggeo =  $news->id . "|" . $splitEstado[0] . "|" . $splitEstado[1] . "|" . $splitEstado[2] . "|";
                                }
                                //*********** ASENTAMIENTO SUMMARY ***********************/
                            }
                            //*********** MUNICIPIO SUMMARY ***********************/
                            $archivoTaggeo = "ninguno.txt";
                            switch ($puntaje) {
                                case 1:
                                    $archivoTaggeo = "taggeo-estado.txt";
                                    break;
                                case 2:
                                    $archivoTaggeo = "taggeo-municipio.txt";
                                    break;
                                case 3:
                                    $archivoTaggeo = "taggeo-asentamiento.txt";
                                    break;
                            }
                            $s = 0;
                            foreach ($this->notasTaggeadas as $t) {
                                if ($t == $news->id) {
                                    $s++;
                                    break;
                                }
                            }
                            if ($s == 0) {
                                $fh = fopen($directorio . $archivoTaggeo, "a+") or die("Se produjo un error al crear el archivo");
                                fwrite($fh, ($dondeTaggeo . "\n")) or die("No se pudo escribir en el archivo");
                                fclose($fh);
                                array_push($this->notasTaggeadas, $news->id);
                            }
                            //\Log::debug($this->notasTaggeadas);
                            break;
                        }
                        //*********** ESTADO SUMMARY ***********************/

                        else {

                            //*********** MUNICIPIO SUMMARY ***********************/
                            $a = $splitEstado[1]; // MUNICIPIO
                            $matches = array_filter($array_contenido, function ($var) use ($a) {
                                //return stristr($var, $a);
                                return preg_match("/$a/i", $var);
                            });

                            if ($matches == 1) { // se ha encontrado el termino

                                $puntaje = 2;
                                $dondeTaggeo =  $news->id . "|" . $splitEstado[0] . "|" . $splitEstado[1] . "|";

                                //*********** ASENTAMIENTO SUMMARY ***********************/
                                $a = $splitEstado[2];
                                $matches = array_filter($array_contenido, function ($var) use ($a) {
                                    //return stristr($var, $a);
                                    return preg_match("/$a/i", $var);
                                });

                                if ($matches == 1) {
                                    $puntaje = 3;
                                    $dondeTaggeo =  $news->id . "|" . $splitEstado[0] . "|" . $splitEstado[1] . "|" . $splitEstado[2] . "|";
                                }
                                //*********** ASENTAMIENTO SUMMARY ***********************/
                                $archivoTaggeo = "ninguno.txt";
                                switch ($puntaje) {
                                    case 1:
                                        $archivoTaggeo = "taggeo-estado.txt";
                                        break;
                                    case 2:
                                        $archivoTaggeo = "taggeo-municipio.txt";
                                        break;
                                    case 3:
                                        $archivoTaggeo = "taggeo-asentamiento.txt";
                                        break;
                                }
                                $s = 0;
                                foreach ($this->notasTaggeadas as $t) {
                                    if ($t == $news->id) {
                                        $s++;
                                        break;
                                    }
                                }
                                if ($s == 0) {
                                    $fh = fopen($directorio . $archivoTaggeo, "a+") or die("Se produjo un error al crear el archivo");
                                    fwrite($fh, ($dondeTaggeo . "\n")) or die("No se pudo escribir en el archivo");
                                    fclose($fh);
                                    array_push($this->notasTaggeadas, $news->id);
                                }
                                //\Log::debug($this->notasTaggeadas);
                                break;
                            }
                            //*********** MUNICIPIO SUMMARY ***********************/

                            else {

                                //*********** ASENTAMIENTO SUMMARY ***********************/
                                $a = $splitEstado[2];
                                $matches = array_filter($array_contenido, function ($var) use ($a) {
                                    //return stristr($var, $a);
                                    return preg_match("/$a/i", $var);
                                });

                                if ($matches == 1) {
                                    $puntaje = 3;
                                    $dondeTaggeo =  $news->id . "|" . $splitEstado[0] . "|" . $splitEstado[1] . "|" . $splitEstado[2] . "|";
                                    $archivoTaggeo = "ninguno.txt";
                                    switch ($puntaje) {
                                        case 1:
                                            $archivoTaggeo = "taggeo-estado.txt";
                                            break;
                                        case 2:
                                            $archivoTaggeo = "taggeo-municipio.txt";
                                            break;
                                        case 3:
                                            $archivoTaggeo = "taggeo-asentamiento.txt";
                                            break;
                                    }
                                    $s = 0;
                                    foreach ($this->notasTaggeadas as $t) {
                                        if ($t == $news->id) {
                                            $s++;
                                            break;
                                        }
                                    }
                                    if ($s == 0) {
                                        $fh = fopen($directorio . $archivoTaggeo, "a+") or die("Se produjo un error al crear el archivo");
                                        fwrite($fh, ($dondeTaggeo . "\n")) or die("No se pudo escribir en el archivo");
                                        fclose($fh);
                                        array_push($this->notasTaggeadas, $news->id);
                                    }
                                    //\Log::debug($this->notasTaggeadas);
                                    break;
                                }
                                //*********** ASENTAMIENTO SUMMARY ***********************/
                            }
                        }
                    }
                }
            }

            unset($news);
            unset($json_estado);
        } catch (Exception $ex) {
            \Log::error($ex);
            throw new Exception($ex->getMessage());
        }
    }

    private function TaggeoNewsResena($news)
    {
        try {
            ini_set('memory_limit', '-1');

            $directorio = "public/taggeo/" . date("Y") . "/" . date("m") . "/" . date("d") . "/";
            $splitEstado = array();
            $json_estado = json_decode(json_encode($this->cacheEstados));

            foreach ($json_estado as $estado) {
                foreach ($estado as $item) {
                    echo "Buscando en la reseña " . $news->id . " - ";
                    echo $item->url . "\n";
                    $dondeTaggeo = "";
                    $puntaje = 0;
                    $splitEstado = explode("|", $item->url);

                    //*********** ESTADO SUMMARY ***********************/
                    $array_contenido = explode(" ", strtolower($news->summary));
                    $a = $splitEstado[0];
                    $matches = array_filter($array_contenido, function ($var) use ($a) {
                        //return stristr($var, $a);
                        return preg_match("/$a/i", $var);
                    });

                    if ($matches == 1) {
                        $puntaje = 1;
                        $dondeTaggeo =  $news->id . "|" . $splitEstado[0] . "|";

                        //*********** MUNICIPIO SUMMARY ***********************/
                        $a = $splitEstado[1]; // MUNICIPIO
                        $matches = array_filter($array_contenido, function ($var) use ($a) {
                            //return stristr($var, $a);
                            return preg_match("/$a/i", $var);
                        });

                        if ($matches == 1) {
                            $puntaje = 2;
                            $dondeTaggeo =  $news->id . "|" . $splitEstado[0] . "|" . $splitEstado[1] . "|";

                            //*********** ASENTAMIENTO SUMMARY ***********************/
                            $a = $splitEstado[2];
                            $matches = array_filter($array_contenido, function ($var) use ($a) {
                                //return stristr($var, $a);
                                return preg_match("/$a/i", $var);
                            });

                            if ($matches == 1) {
                                $puntaje = 3;
                                $dondeTaggeo =  $news->id . "|" . $splitEstado[0] . "|" . $splitEstado[1] . "|" . $splitEstado[2] . "|";
                            }
                            //*********** ASENTAMIENTO SUMMARY ***********************/
                        }
                        //*********** MUNICIPIO SUMMARY ***********************/
                        $archivoTaggeo = "ninguno.txt";
                        switch ($puntaje) {
                            case 1:
                                $archivoTaggeo = "taggeo-estado.txt";
                                break;
                            case 2:
                                $archivoTaggeo = "taggeo-municipio.txt";
                                break;
                            case 3:
                                $archivoTaggeo = "taggeo-asentamiento.txt";
                                break;
                        }
                        $fh = fopen($directorio . $archivoTaggeo, "a+") or die("Se produjo un error al crear el archivo");
                        fwrite($fh, ($dondeTaggeo . "\n")) or die("No se pudo escribir en el archivo");
                        fclose($fh);
                        break;
                    }
                    //*********** ESTADO SUMMARY ***********************/

                    else {

                        //*********** MUNICIPIO SUMMARY ***********************/
                        $a = $splitEstado[1]; // MUNICIPIO
                        $matches = array_filter($array_contenido, function ($var) use ($a) {
                            //return stristr($var, $a);
                            return preg_match("/$a/i", $var);
                        });

                        if ($matches == 1) { // se ha encontrado el termino

                            $puntaje = 2;
                            $dondeTaggeo =  $news->id . "|" . $splitEstado[0] . "|" . $splitEstado[1] . "|";

                            //*********** ASENTAMIENTO SUMMARY ***********************/
                            $a = $splitEstado[2];
                            $matches = array_filter($array_contenido, function ($var) use ($a) {
                                //return stristr($var, $a);
                                return preg_match("/$a/i", $var);
                            });

                            if ($matches == 1) {
                                $puntaje = 3;
                                $dondeTaggeo =  $news->id . "|" . $splitEstado[0] . "|" . $splitEstado[1] . "|" . $splitEstado[2] . "|";
                            }
                            //*********** ASENTAMIENTO SUMMARY ***********************/
                            $archivoTaggeo = "ninguno.txt";
                            switch ($puntaje) {
                                case 1:
                                    $archivoTaggeo = "taggeo-estado.txt";
                                    break;
                                case 2:
                                    $archivoTaggeo = "taggeo-municipio.txt";
                                    break;
                                case 3:
                                    $archivoTaggeo = "taggeo-asentamiento.txt";
                                    break;
                            }
                            $fh = fopen($directorio . $archivoTaggeo, "a+") or die("Se produjo un error al crear el archivo");
                            fwrite($fh, ($dondeTaggeo . "\n")) or die("No se pudo escribir en el archivo");
                            fclose($fh);
                            break;
                        }
                        //*********** MUNICIPIO SUMMARY ***********************/

                        else {

                            //*********** ASENTAMIENTO SUMMARY ***********************/
                            $a = $splitEstado[2];
                            $matches = array_filter($array_contenido, function ($var) use ($a) {
                                //return stristr($var, $a);
                                return preg_match("/$a/i", $var);
                            });

                            if ($matches == 1) {
                                $puntaje = 3;
                                $dondeTaggeo =  $news->id . "|" . $splitEstado[0] . "|" . $splitEstado[1] . "|" . $splitEstado[2] . "|";
                                $archivoTaggeo = "ninguno.txt";
                                switch ($puntaje) {
                                    case 1:
                                        $archivoTaggeo = "taggeo-estado.txt";
                                        break;
                                    case 2:
                                        $archivoTaggeo = "taggeo-municipio.txt";
                                        break;
                                    case 3:
                                        $archivoTaggeo = "taggeo-asentamiento.txt";
                                        break;
                                }
                                $fh = fopen($directorio . $archivoTaggeo, "a+") or die("Se produjo un error al crear el archivo");
                                fwrite($fh, ($dondeTaggeo . "\n")) or die("No se pudo escribir en el archivo");
                                fclose($fh);
                                break;
                            }
                            //*********** ASENTAMIENTO SUMMARY ***********************/
                            else {
                                /*
                                $dondeTaggeo .= $news->id ."|NO SE ENCONTRO EN CONTENIDO NI RESEÑA";
                                $fh = fopen($directorio . "NotaNoTaggeada.txt", "a+") or die("Se produjo un error al crear el archivo");
                                fwrite($fh, ($dondeTaggeo . "\n")) or die("No se pudo escribir en el archivo");
                                fclose($fh);
                                */
                                return false;
                                break;
                            }
                        }
                    }
                }
            }

            unset($news);
            unset($json_estado);
        } catch (Exception $ex) {
            \Log::error($ex);
            throw new Exception($ex->getMessage());
        }
    }

    private function TaggeoNewsTitulo($news)
    {
        try {
            ini_set('memory_limit', '-1');

            $directorio = "public/taggeo/" . date("Y") . "/" . date("m") . "/" . date("d") . "/";
            $splitEstado = array();
            $json_estado = json_decode(json_encode($this->cacheEstados));

            foreach ($json_estado as $estado) {
                foreach ($estado as $item) {
                    $dondeTaggeo = "";
                    $puntaje = 0;
                    $splitEstado = explode("|", $item->url);

                    //*********** ESTADO TITULO ***********************/
                    $a = $splitEstado[0]; // ESTADO
                    $array_contenido = explode(" ", strtolower($news->title)); // BUSCAR EN TITLE

                    $matches = array_filter($array_contenido, function ($var) use ($a) {
                        //return stristr($var, $a);
                        return preg_match("/$a/i", $var);
                    });

                    if ($matches == 1) {
                        $puntaje = 1;
                        $dondeTaggeo =  $news->id . "|" . $splitEstado[0] . "|";

                        //*********** MUNICIPIO TITULO ***********************/
                        $a = $splitEstado[1];
                        $matches = array_filter($array_contenido, function ($var) use ($a) {
                            return stristr($var, $a);
                            //return preg_match("/$a/i", $var);
                        });

                        if ($matches == 1) {
                            $puntaje = 2;
                            $dondeTaggeo =  $news->id . "|" . $splitEstado[0] . "|" . $splitEstado[1] . "|";

                            //*********** ASENTAMIENTO TITULO ***********************/
                            $a = $splitEstado[2];
                            $matches = array_filter($array_contenido, function ($var) use ($a) {
                                //return stristr($var, $a);
                                return preg_match("/$a/i", $var);
                            });

                            if ($matches == 1) {
                                $puntaje = 3;
                                $dondeTaggeo =  $news->id . "|" . $splitEstado[0] . "|" . $splitEstado[1] . "|" . $splitEstado[2] . "|";
                            }
                            //*********** ASENTAMIENTO TITULO ***********************/
                        }
                        //*********** MUNICIPIO TITULO ***********************/

                        //echo $dondeTaggeo . " ==> " . $puntaje . " ESTADO TITULO\n";
                        $archivoTaggeo = "ninguno.txt";
                        switch ($puntaje) {
                            case 1:
                                $archivoTaggeo = "taggeo-estado.txt";
                                break;
                            case 2:
                                $archivoTaggeo = "taggeo-municipio.txt";
                                break;
                            case 3:
                                $archivoTaggeo = "taggeo-asentamiento.txt";
                                break;
                        }
                        $fh = fopen($directorio . $archivoTaggeo, "a+") or die("Se produjo un error al crear el archivo");
                        fwrite($fh, ($dondeTaggeo . "\n")) or die("No se pudo escribir en el archivo");
                        fclose($fh);
                        break;
                    }
                    //*********** ESTADO TITULO ***********************/
                    else {
                        //*********** MUNICIPIO TITULO ***********************/
                        $a = $splitEstado[1];
                        $matches = array_filter($array_contenido, function ($var) use ($a) {
                            //return stristr($var, $a);
                            return preg_match("/$a/i", $var);
                        });

                        if ($matches == 1) {
                            $puntaje = 2;
                            $dondeTaggeo =  $news->id . "|" . $splitEstado[0] . "|" . $splitEstado[1] . "|";

                            //*********** ASENTAMIENTO TITULO ***********************/
                            $a = $splitEstado[2];
                            $matches = array_filter($array_contenido, function ($var) use ($a) {
                                //return stristr($var, $a);
                                return preg_match("/$a/i", $var);
                            });

                            if ($matches == 1) {
                                $puntaje = 3;
                                $dondeTaggeo =  $news->id . "|" . $splitEstado[0] . "|" . $splitEstado[1] . "|" . $splitEstado[2] . "|";
                            }
                            //*********** ASENTAMIENTO TITULO ***********************/
                            //echo $dondeTaggeo . " ==> " . $puntaje . " MUNICIPIO TITULO\n";
                            $archivoTaggeo = "ninguno.txt";
                            switch ($puntaje) {
                                case 1:
                                    $archivoTaggeo = "taggeo-estado.txt";
                                    break;
                                case 2:
                                    $archivoTaggeo = "taggeo-municipio.txt";
                                    break;
                                case 3:
                                    $archivoTaggeo = "taggeo-asentamiento.txt";
                                    break;
                            }
                            $fh = fopen($directorio . $archivoTaggeo, "a+") or die("Se produjo un error al crear el archivo");
                            fwrite($fh, ($dondeTaggeo . "\n")) or die("No se pudo escribir en el archivo");
                            fclose($fh);
                            break;
                        }
                        //*********** MUNICIPIO TITULO ***********************/
                        else {

                            //*********** ASENTAMIENTO TITULO ***********************/
                            $a = $splitEstado[2];
                            $matches = array_filter($array_contenido, function ($var) use ($a) {
                                //return stristr($var, $a);
                                return preg_match("/$a/i", $var);
                            });

                            if ($matches == 1) {
                                $puntaje = 3;
                                $dondeTaggeo =  $news->id . "|" . $splitEstado[0] . "|" . $splitEstado[2] . "|" . $splitEstado[2];
                                //echo $dondeTaggeo . " ==> " . $puntaje . " ASENTAMIENTO TITULO\n";
                                $archivoTaggeo = "ninguno.txt";
                                switch ($puntaje) {
                                    case 1:
                                        $archivoTaggeo = "taggeo-estado.txt";
                                        break;
                                    case 2:
                                        $archivoTaggeo = "taggeo-municipio.txt";
                                        break;
                                    case 3:
                                        $archivoTaggeo = "taggeo-asentamiento.txt";
                                        break;
                                }
                                $fh = fopen($directorio . $archivoTaggeo, "a+") or die("Se produjo un error al crear el archivo");
                                fwrite($fh, ($dondeTaggeo . "\n")) or die("No se pudo escribir en el archivo");
                                fclose($fh);
                                break;
                            }
                            //*********** ASENTAMIENTO TITULO ***********************/

                            else {
                                $dondeTaggeo .= "|NO SE ENCONTRO EN EL TITULO";
                                $fh = fopen($directorio . "NotaNoTaggeada.txt", "a+") or die("Se produjo un error al crear el archivo");
                                fwrite($fh, ($dondeTaggeo . "\n")) or die("No se pudo escribir en el archivo");
                                fclose($fh);
                                break;
                            }
                        }

                        break;
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
