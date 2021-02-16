<?php

namespace App\Jobs;

use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class TaggeoNoticiasSQLite implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $db;
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
        try {
            $this->db = DB::connection('sqlite');

            $this->CrearMatrixdeDatos();
            $this->CagarEstadosMemoria();
            $this->BuscarGrepLinux();
        } catch (Exception $ex) {
            \Log::error($ex);
            var_dump($ex);
        }
    }

    private function CrearMatrixdeDatos()
    {
        ini_set('memory_limit', '-1');

        $articulos = array(
            " a ", " tu ", " y ", " con ", " la ", " este ", " de ", " que ", " se ", " debe ", " una ", " pero ", " los ", " las ", " tus ", " para ", " el ",
            " uno ", " un ", " unos ", " desde ", " usa ", " ten ", " lo ", " mas ", " menos ", " cuando ", " donde ", " uso ", " su ", " ya ", " on ", " en ",
            " esta ", " sí ", " tú ", " pasó ", " sus ", " casi ", " no ", " del ", " por ", " tras ", " al ", " los ", " han ", " ni ", " entre ", " es ", " ser ",
            " ha ", " fue ", " está ", " estos ", " están ", " mis ", " haber ", " los ", " haz ", " sol ", " muy ", " así ", " mi ", "me ", " ese ", " todo ", " hay ", " como ",
            " ello ", " le ", " cómo ", " más ", " me ", " son ", " muy ", " bien ", " solo ", " además ", " nuevo ", " yo ", " esté ", " he ", " hecho ", " nos ", " sólo ",
            " sobre ", " entre ", " alado ", " lado ", " si ", " sé ", " estoy ", " cuáles ", " quién ", " estoy ", " sé ", " o ", " vez ", " tiene ", " día ", " casa ", " tía ",
            " cual ", " van ", " estar ", " cual ", " será ", " e ", " sin ", " eso ", " var ", "th ", " tal ", " te ", " ves ", " hace ", " vaya ",
        );

        $eliminar = array(
            "“", ";", ".", ",", "[…]", '"', "\\", "/", ":", "+", "*" . "[", "]", "(", ")", "$", "¿", "?", "¡", "!", "=", "*", "-", "'", "+", "{", "}", "_", "^", "`", "~", "¨", "¸", "|", "@", "#", "¬", "°", "1", "2", "3", "4", "5", "6", "7", "8", "9", "0",
            '”', "…", "<", ">",
        );

        try {
            $this->db->table('matrix')->delete();
            $news = $this->db->table('news')->select('id', 'content', 'summary', 'title')->get();

            foreach ($news as $item) {
                $directorio = "public/matrix/" . $item->id . ".txt";

                if (file_exists($directorio)) {
                    unlink($directorio);
                }

                $fh = fopen($directorio, "a+") or die("Se produjo un error al crear el archivo");

                echo $item->id . " - " . $item->title . "\n";

                $contenido = strip_tags(strtolower(str_replace(array("\n", "\t", "\\"), "", $item->content)));
                $contenido = str_replace(array("Á", "É", "Í", "Ó", "Ú"), array("á", "é", "í", "ó", "ú"), $contenido);

                fwrite($fh, ($item->id . "\n")) or die("No se pudo escribir en el archivo");
                fwrite($fh, ($item->title . "\n")) or die("No se pudo escribir en el archivo");
                fwrite($fh, ($item->summary . "\n")) or die("No se pudo escribir en el archivo");
                fwrite($fh, ($item->content . "\n")) or die("No se pudo escribir en el archivo");
                fclose($fh);
            }
        } catch (Exception $ex) {
            throw new Exception($ex->getMessage());
        }
    }

    private function AgregarNotasEstadoMunicipio()
    {
        $quitar = array(
            'colonia', 'pueblo', 'barrio', 'equipamiento', 'campamento', 'aeropuerto', 'fraccionamiento', 'condominio', 'unidad habitacional', 'zona comercial',
            'rancho', 'ranchería', 'parque industrial', 'granja', 'ejido', 'zona federal', 'zona industrial', 'hacienda', 'paraje', 'zonamilitar', 'residencial', 'granusuario',
            'puerto', 'ampliación', 'conjunto habitaciona', 'poblado comunal', 'club de golf', 'ingenio', 'villa', 'congregación', 'exhacienda', 'finca', 'estación', 'zonanaval'
        );

        try {
            $this->db->table('taggeos')->delete();
            //$query = "INSERT INTO taggeos (news, estado, municipio, asentamiento, cp, copo, fecha) SELECT DISTINCT id as news,estado,municipio,'' as asentamiento,'' as cp,'' as copo, '" . date('Y-m-d H:i:s') . "' FROM vwRelacionNotas;";
            //$this->db->unprepared($query);

            $estados = $this->db->table('estados')->select()->orderBy('estado')->get();

            foreach ($estados as $estado) {
                echo "Buscar el asentamiento, " . $estado->asentamiento . " en el contenido\n";
                $query =  "SELECT n.id FROM news n WHERE n.content LIKE '%" . $estado->asentamiento . "%' OR n.content LIKE '%" . $estado->municipio . "%' OR n.content LIKE '%" . $estado->estado . "%' AND n.content <> '' ORDER BY n.id;";
                $resultados = $this->db->select($query);

                foreach ($resultados as $resultado) {
                    $this->db->table('taggeos')->insert([
                        "news" => $resultado->id,
                        "estado" => $estado->estado,
                        "municipio" => $estado->municipio,
                        "asentamiento" => $estado->asentamiento,
                        "cp" => $estado->cp,
                        "copo" => $estado->copo,
                        "fecha" => date('Y-m-d H:i:s')
                    ]);

                    echo "Se agrego " . $resultado->id . " con los datos sig. " . $estado->estado . ", " . $estado->municipio . ", " . $estado->asentamiento . "\n";
                }
            }
        } catch (Exception $ex) {
            throw new Exception($ex->getMessage());
        }
    }

    private function LeerMatrixBuscarCoincidencias()
    {
        try {
            ini_set('memory_limit', '-1');

            $json_estado = json_decode(json_encode($this->cacheEstados));
            $this->db->table('taggeos')->delete();

            $array_encontrado = array();

            echo "====================================================================================\n";
            echo "Iniciando la busqueda de estados, municipios y asentamientos en el contenido de la nota\n";

            $thefolder = "public/matrix/";

            if ($handler = opendir($thefolder)) {
                while (false !== ($archivo = readdir($handler))) {

                    if ($archivo != "." && $archivo != "..") {

                        /*
                        $splitArchivo = explode(".", $archivo);
                        echo "Buscando coincidencias en " . $archivo . " " . date("H:i:s") . "\n";

                        $noticia = file_get_contents("public/matrix/" . $archivo);
                        $noticia = addslashes(html_entity_decode(strip_tags(strtolower(trim($noticia)))));
                        */

                        foreach ($json_estado as $estado) {

                            $valor = 0;
                            $splitEstado = array();

                            foreach ($estado as $item) {

                                $splitEstado = explode("|", $item->url);

                                foreach ($splitEstado as $i) {
                                    $this->CagarEstadosMemoria($archivo, $i);
                                }

                                /*
                                $estadox = $splitEstado[0];
                                $municipiox = $splitEstado[1];
                                $asentamientox = $splitEstado[2];

                                $estado_encontrado = preg_match("/$estadox$/im", $noticia);
                                $municipio_encontrado = preg_match("/$municipiox$/im", $noticia);
                                $asentamiento_encontrado = preg_match("/$asentamientox$/im", $noticia);

                                if ($estado_encontrado == 1 && $municipio_encontrado == 1 && $asentamiento_encontrado == 1) {
                                    echo $valor = 5;
                                } else if ($estado_encontrado == 0 && $municipio_encontrado == 1 && $asentamiento_encontrado == 1) {
                                    echo $valor = 4;
                                } else if ($estado_encontrado == 0 && $municipio_encontrado == 0 && $asentamiento_encontrado == 1) {
                                    echo $valor = 3;
                                } else if ($estado_encontrado == 1 && $municipio_encontrado == 1 && $asentamiento_encontrado == 0) {
                                    echo $valor = 2;
                                } else if ($estado_encontrado == 1 && $municipio_encontrado == 0 && $asentamiento_encontrado == 0) {
                                    echo $valor = 1;
                                }


                                switch ($valor) {
                                    case 1:
                                        break;
                                    case 2:
                                        break;
                                    case 3:
                                        //array_push($array_encontrado, $splitEstado);
                                        break;
                                }
                                */
                            }

                            //\Log::debug($array_encontrado);

                            /*
                            $this->db->table('taggeos')->insert([
                                "news" => $splitArchivo[0],
                                "estado" => $splitEstado[0],
                                "municipio" => $splitEstado[1],
                                "asentamiento" => $splitEstado[2],
                                "cp" => $splitEstado[3],
                                "copo" => $splitEstado[4] . " 5",
                                "fecha" => date('Y-m-d H:i:s')
                            ]);
                            */
                        }
                    }
                }
                closedir($handler);
            }

            echo "Terminando la busqueda de estados, municipios y asentamientos en el contenido de la nota\n";
            echo "====================================================================================\n";

            unset($this->cacheEstados);
        } catch (Exception $ex) {
            throw new Exception($ex->getMessage());
        }
    }

    private function CagarEstadosMemoria()
    {
        try {
            ini_set('memory_limit', '-1');

            echo "====================================================================================\n";
            echo "Iniciando la carga de estados a memoria\n";

            $thefolder = "public/estados/";
            if ($handler = opendir($thefolder)) {

                while (false !== ($archivo = readdir($handler))) {

                    if ($archivo != "." && $archivo != "..") {

                        $datos_estado = file_get_contents("public/estados/" . $archivo);
                        $json_estado = json_decode($datos_estado, true);
                        $json_estado = $json_estado;

                        array_push($this->cacheEstados, $json_estado);

                        unset($datos_estado);
                        unset($json_estado);
                    }
                }
                closedir($handler);
            }

            echo "Terminando la carga de estados a memoria\n";
            echo "====================================================================================\n";
        } catch (Exception $ex) {
            \Log::error($ex->getMessage());
            throw new Exception($ex->getMessage());
        }
    }

    private function BuscarPalabra($rutaSimbolos, $palabra)
    {
        $rutaSimbolos = "tabla_de_simbolos.txt";
        $archivoTds1 = fopen($rutaSimbolos, "r+");

        $x = 1;

        while (!feof($archivoTds1)) {

            $busca = fgets($archivoTds1);

            //if (strstr($busca, $palabra))
            if (strpos($busca, $palabra)) {

                echo "Palabra encontrada: $palabra\n";

                return;
            } else {
                echo 'No se encontro la palabra ' . $palabra . ', es un Infiltrado (Verifique Mayusculas y Minusculas)';
            }

            $x++;
        }

        fclose($archivoTds1);
        return $archivoTds1;
    }

    private function BuscarGrepLinux()
    {
        try {
            $json_estado = json_decode(json_encode($this->cacheEstados));

            $files = glob('public/grep/*'); //obtenemos todos los nombres de los ficheros
            foreach ($files as $file) {
                if (is_file($file))
                    unlink($file); //elimino el fichero
            }

            $fh = fopen("public/grep/command.txt", "a+") or die("Se produjo un error al crear el archivo");

            foreach ($json_estado as $estados) {
                foreach ($estados as $estado) {
                    $archivo = str_replace(array(" ", "|"), array("-", "_"), $estado->url) . ".txt";
                    $command = sprintf('grep -E -io "%s"  public/matrix/*.txt >> public/grep/%s', $estado->url, $archivo);
                    shell_exec($command);
                    echo $command . "\n";

                    //$contenido = strip_tags(strtolower(str_replace(array("\n", "\t", "\\"), "", $item->content)));
                    //$contenido = str_replace(array("Á", "É", "Í", "Ó", "Ú"), array("á", "é", "í", "ó", "ú"), $contenido);

                    fwrite($fh, ($command. "\n")) or die("No se pudo escribir en el archivo");
                }
            }

            fclose($fh);

        } catch (Exception $ex) {
            \Log::error($ex->getMessage());
            throw new Exception($ex->getMessage());
        }
    }
}
