<?php

namespace App\Console\Commands;

use Exception;
use App\Jobs\BuscarPalabraContenido;
use App\Jobs\BuscarPalabraResena;
use App\Jobs\BuscarPalabraTitulo;
use App\Jobs\BuscarPalabraMatriz;
use App\Jobs\TaggearNota;
use App\Jobs\TaggeoNoticiasAsentamiento;
use App\Jobs\TaggeoNoticiasSQLite;
use Illuminate\Support\Facades\DB;

use Illuminate\Console\Command;

class TaggeoNews extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'taggeo:news';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command Taggeo News';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        try {
            echo "===== Proceso Iniciando ====== \n";
            //$this->CraerNoticias();
            //$this->CrearEstadosMunicipiosUrl();
            //$this->TaggearNotasPorUrls();

            $this->TaggearNotaSQLite();


            echo "===== Proceso Terminado ====== \n";
        } catch (Exception $ex) {
            \Log::error($ex->getMessage());
            var_dump($ex);
        }
    }

    private function CraerNoticias()
    {
        try {
            ini_set('memory_limit', '-1');
            $db = DB::connection('sqlite');

            $contenido = "";
            $resena = "";
            $titulo = "";

            $news = DB::select("SELECT id, title, summary, content FROM news WHERE created_at >= now() - INTERVAL 1 DAY AND id_author != 100 AND id_status_news = 2 AND url IS NULL ORDER BY id;");

            $db->table('news')->delete();

            $articulos = array(
                "“", "”", ";", ".", ",", "[…]", '"', "\\", " / ", ":", " a ", " tu ", " y ", " con ", " la ", " este ", " de ", " que ", " se ", " debe ", " una ", " pero ", " los ", " las ", " tus ", " para ", " el ",
                " uno ", " un ", " unos ", " desde ", " usa ", " ten ", " lo ", " mas ", " menos ", " cuando ", " donde ", " uso ", " su ", " ya ", " on ", " en ",
                " esta ", " sí ", " tú ", " pasó ", " sus ", " casi ", " no ", " del ", " por ", " tras ", " al ", " los ", " han ", " ni ", " entre ", " es ", " ser ",
                " ha ", " fue ", " está ", " estos ", " están ", " mis ", " haber ", "los ", "haz ", " haz ", " sol ", " muy ", " así ", " mi ", "me ", " ese ", " todo ", " hay ", " como ",
                " ello ", " le ", " cómo ", " más ", " me ", " son ", " muy ", " bien ", " solo ", " además ", " nuevo ", " yo ", " esté ", " he ", " hecho ", " nos ", " sólo ",
                " sobre ", " entre ", " alado ", " lado ", " si ", " sé ", " estoy ", " cuáles ", " quién ", " estoy ", " sé ", " o ", " vez ", " tiene ", " día ", " casa ", " tía ",
            );

            $count = 1;

            foreach ($news as $new) {
                if ($new->content !== "") {
                    $contenido = strip_tags(strtolower(str_replace(array("\n", "\t", "\\"), "", $new->content)));
                    $contenido = str_replace(array("Á", "É", "Í", "Ó", "Ú"),  array("á", "é", "í", "ó", "ú"), $contenido);
                    //$contenido = str_replace($articulos,  " ", $contenido);
                }
                $resena = strtolower(str_replace(array("\n", "\t", "\\"), "", $new->summary));
                $resena = str_replace(array("Á", "É", "Í", "Ó", "Ú"),  array("á", "é", "í", "ó", "ú"), $resena);
                $titulo = strtolower(str_replace(array("Á", "É", "Í", "Ó", "Ú"),  array("á", "é", "í", "ó", "ú"), $new->title));

                $db->table('news')->insert([
                    "id" => $new->id,
                    "title" => $titulo,
                    "summary" => $resena,
                    "content" => $contenido,
                    "estado" => "",
                    "municipio" => "",
                    "asentamiento" => "",
                    "cp" => "",
                    "copo" => ""
                ]);

                echo "Noticia agregada " . $new->id ." - ". $new->title . "\n";
                $count++;
            }

            echo "Se agregoron los registros en la tabla news, registros agregados " . $count ."\n";
            return true;
        } catch (Exception $ex) {
            \Log::error($ex->getMessage());
            throw new Exception($ex->getMessage());
        }
    }

    private function CrearEstadosMunicipios()
    {
        try {
            ini_set('memory_limit', '-1');

            $estadoUrl = "";
            $municipioUrl = "";
            $asentamientoUrl = "";

            /**
             * Crear Estados, Municipios y Asentamientos
             */

            $estados = \DB::select("SELECT DISTINCT c_estado, d_estado, COUNT(c_municipio) as c_municipio FROM postal_codes WHERE d_asenta != 'México' GROUP BY d_estado ORDER BY c_municipio;");
            foreach ($estados as $estado) {

                $objEstados = array();
                $objMunicipios = array();
                $objAsentamientos = array();

                $archivo = "public/estados/" . str_replace(array(" ", "á", "é", "í", "ó", "ú", "ñ"), array("-", "a", "e", "i", "o", "u", "n"), strtolower($estado->d_estado)) . ".json";
                if (file_exists($archivo)) unlink($archivo);

                if (!file_exists($archivo)) {
                    $municipios = \DB::select("SELECT DISTINCT c_municipio, d_municipio, COUNT(id_asenta_cpcons) as id_asenta, d_codigo FROM postal_codes WHERE d_asenta != 'México' AND c_estado = " . $estado->c_estado . " GROUP BY c_municipio ORDER BY id_asenta;");
                    $objM = array();

                    foreach ($municipios as $municipio) {

                        $query = sprintf("SELECT DISTINCT d_estado, d_municipio, c.id_asenta_cpcons, c.d_tipo_asenta, c.d_asenta, c.d_codigo, c.id,
                                            CONCAT(d_tipo_asenta, ' ', d_asenta) as nom_asentamiento, IFNULL(s.title, '') as copo
                                            FROM copopro.postal_codes c
                                                LEFT JOIN copos_postalcodes p ON (p.postal_code_id = c.d_codigo)
                                                LEFT JOIN copos s ON (s.id = p.copo_id)
                                            WHERE c.d_asenta != 'México' AND c.c_estado = %s AND c.c_municipio = %s
                                            GROUP BY c.d_asenta ORDER BY c.d_estado;", $estado->c_estado, $municipio->c_municipio);

                        $asentamientos = \DB::select($query);
                        $objA = array();
                        foreach ($asentamientos as $asentamiento) {
                            $objA = array(
                                "id" => $asentamiento->id_asenta_cpcons,
                                "nom_asentamiento" => $asentamiento->nom_asentamiento,
                                "d_tipo_asenta" => $asentamiento->d_tipo_asenta,
                                "d_asenta" => $asentamiento->d_asenta,
                                "cp" => $asentamiento->d_codigo,
                                "idPostalCode" => $asentamiento->id,
                                "copo" => $asentamiento->copo
                            );

                            $asentamientoUrl = $asentamiento->nom_asentamiento . "|" . $asentamiento->d_codigo . "|" . $asentamiento->copo;
                            array_push($objAsentamientos, $objA);
                            unset($objA);
                        }

                        $objM = array(
                            "id" => $municipio->c_municipio,
                            "municipio" => $municipio->d_municipio,
                            "cp" => $municipio->d_codigo,
                            "asentamientos" => $objAsentamientos
                        );

                        $municipioUrl = $municipio->d_municipio;
                        array_push($objMunicipios, $objM);
                        unset($objM);
                    }

                    $estadoUrl = $estado->d_estado . "|" . $municipioUrl . "|" . $asentamientoUrl;

                    $obj = array(
                        "id" => $estado->c_estado,
                        "estado" => $estado->d_estado,
                        "cp" => 0,
                        "url" => $estadoUrl,
                        "municipios" => $objMunicipios
                    );

                    $estadoUrl = $estado->d_estado;

                    array_push($objEstados, $obj);
                    unset($obj);

                    echo "Estado " . $estado->d_estado . " agregado al array con sus municipios y asentamientos\n";

                    $json = json_encode($objEstados, JSON_UNESCAPED_UNICODE);
                    file_put_contents($archivo, $json);
                } else {
                    echo "El archivo " . $archivo . " ya existe\n";
                }
            }

            echo "Se crearon las Estados, Municipios y Asentamientos Json\n";
        } catch (Exception $ex) {
            \Log::error($ex->getMessage());
            throw new Exception($ex->getMessage());
        }
    }

    private function CrearEstadosMunicipiosUrl()
    {
        try {
            ini_set('memory_limit', '-1');

            $count = 1;
            $db = DB::connection('sqlite');
            $db->table('estados')->delete();

            $estados = \DB::select("SELECT c.c_estado, c.d_estado FROM copopro.postal_codes c WHERE c.d_asenta != 'México' GROUP BY c.c_estado,c.d_estado ORDER BY c.d_estado;");

            foreach ($estados as $estado) {

                $query = sprintf("SELECT DISTINCT
                                    c.id,
                                    c.c_estado,
                                    c.d_estado,
                                    c.d_municipio,
                                    CONCAT(c.d_tipo_asenta, ' ', c.d_asenta) as nom_asentamiento,
                                    c.d_asenta,
                                    c.d_codigo,
                                    IFNULL(s.title, 'sin copo') as copo
                                FROM copopro.postal_codes c
                                    LEFT JOIN copopro.copos_postalcodes p ON (p.postal_code_id = c.d_codigo)
                                    LEFT JOIN copopro.copos s ON (s.id = p.copo_id)
                                WHERE c.d_asenta != 'México' AND c.c_estado  = %s
                                ORDER BY c.d_estado, c.d_municipio, nom_asentamiento;", $estado->c_estado);

                $municipios = \DB::select($query);

                foreach ($municipios as $municipio) {
                    $asenta = str_replace(array("(", ")", "[", "]", "{", "}", "'", '"', ".", ";", ":", "/", "\\", "?", "*", "!", "&", "%", "$", "#", "¡", "¿"), "", $municipio->nom_asentamiento);

                    $db->table('estados')->insert([
                        "estado" => strtolower($municipio->d_estado),
                        "municipio" => strtolower($municipio->d_municipio),
                        "asentamiento" => strtolower($asenta),
                        "cp" => $municipio->d_codigo,
                        "copo" => $municipio->copo
                    ]);

                    echo $count++ ." Estado agregado ". $municipio->d_estado .", ".$municipio->d_municipio.", ".$asenta."\n";

                }

                echo "Registros agregados " . $count."\n";
            }

            echo "Se crearon las Estados, Municipios y Asentamientos Json\n";
        } catch (Exception $ex) {
            \Log::error($ex->getMessage());
            throw new Exception($ex->getMessage());
        }
    }

    private function TaggearNotasEstadoMunicipioAsentamiento()
    {
        try {
            TaggearNota::dispatch();
            BuscarPalabraTitulo::dispatch();
            BuscarPalabraResena::dispatch();
            BuscarPalabraContenido::dispatch();
            BuscarPalabraMatriz::dispatch();
        } catch (Exception $ex) {
            \Log::error($ex->getMessage());
            throw new Exception($ex->getMessage());
        }
    }

    private function TaggearNotasPorUrls()
    {
        try {
            TaggeoNoticiasAsentamiento::dispatch();
        } catch (Exception $ex) {
            \Log::error($ex->getMessage());
            throw new Exception($ex->getMessage());
        }
    }

    private function TaggearNotaSQLite(){
        try{
            TaggeoNoticiasSQLite::dispatch();
        }
        catch (Exception $ex){
            \Log::error($ex->getMessage());
            throw new Exception($ex->getMessage());
        }
    }
}
