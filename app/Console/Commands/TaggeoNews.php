<?php

namespace App\Console\Commands;

use App\Jobs\BuscarPalabraContenido;
use App\Jobs\BuscarPalabraResena;
use App\Jobs\BuscarPalabraTitulo;
use Exception;
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
            $this->CraerNoticias();
            $this->CrearEstadosMunicipios();
            //$this->TaggearNotasEstadoMunicipioAsentamiento();
            echo "===== Proceso Terminado ====== \n";
        } catch (Exception $ex) {
            \Log::error($ex->getMessage());
        }
    }

    private function CraerNoticias()
    {
        try {
            ini_set('memory_limit', '-1');

            $objNews = array();

            /**
             * Crear Noticias
             */

            $archivo = "public/news/news-" . date("Y-m-d") . ".json";
            if (file_exists($archivo)) {
                unlink($archivo);
                echo "Se elimino el archivo ".$archivo."\n";
                //echo "El archivo ".$archivo." ya existe\n";
                //return true;
            }

            $news = \DB::select("SELECT id, title, summary, content FROM news
            WHERE created_at >= now() - INTERVAL 1 DAY AND id_author != 100
            AND id_status_news = 2 AND url IS NULL ORDER BY created_at DESC");

            $articulos = array(
                "“", "”", ";", ".", ",", "[…]", '"', "\\", " / ", ":", " a ", " tu ", " y ", " con ", " la ", " este ", " de ", " que ", " se ", " debe ", " una ", " pero ", " los ", " las ", " tus ", " para ", " el ",
                " uno ", " un ", " unos ", " desde ", " usa ", " ten ", " lo ", " mas ", " menos ", " cuando ", " donde ", " uso ", " su ", " ya ", " on ", " en ",
                " esta ", " sí ", " tú ", " pasó ", " sus ", " casi ", " no ", " del ", " por ", " tras ", " al ", " los ", " han ", " ni ", " entre ", " es ", " ser ",
                " ha ", " fue ", " está ", " estos ", " están ", " mis ", " haber ", "los ", "haz ", " haz ", " sol ", " muy ", " así ", " mi ", "me ", " ese ", " todo ", " hay ", " como ",
                " ello ", " le ", " cómo ", " más ", " me ", " son ", " muy ", " bien ", " solo ", " además ", " nuevo ", " yo ", " esté ", " he ", " hecho ", " nos ", " sólo ",
                " sobre ", " entre ", " alado ", " lado ", " si ", " sé ", " estoy ", " cuáles ", " quién ", " estoy ", " sé ", " o ", " vez ", " tiene ", " día ", " casa ", " tía ",
            );

            foreach ($news as $new) {
                $contenido = strip_tags(strtolower(str_replace(array("\n", "\t", "\\"), "", $new->content)));
                $contenido = str_replace($articulos, " ", $contenido);

                $resena = strtolower(str_replace(array("\n", "\t", "\\"), "", $new->summary));
                $resena = str_replace($articulos, " ", $resena);

                $titulo = strtolower(str_replace($articulos, " ", $new->title));

                $obj = array(
                    "id" => $new->id,
                    "title" => $titulo,
                    "summary" => $resena,
                    "content" => $contenido,
                    "estado" => 0,
                    "municipio" => 0,
                    "asentamiento" => 0,
                    "cp" => 0,
                    "idPostalCode" => 0
                );

                array_push($objNews, $obj);

                unset($obj);
            }

            $count = count($objNews);

            $json = json_encode($objNews, JSON_UNESCAPED_UNICODE);
            file_put_contents($archivo, $json);
            echo "Se creo el archivo ".$archivo. " número de notas ". $count."\n";
            unset($objNews);
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

            /**
             * Crear Estados, Municipios y Asentamientos
             */

            $estados = \DB::select("SELECT DISTINCT c_estado, d_estado, COUNT(c_municipio) as c_municipio FROM postal_codes WHERE d_asenta != 'México' GROUP BY d_estado ORDER BY c_municipio;");
            foreach ($estados as $estado) {

                $objEstados = array();
                $objMunicipios = array();
                $objAsentamientos = array();

                $archivo = "public/json/" . str_replace(array(" ", "á", "é", "í", "ó", "ú", "ñ"), array("-", "a", "e", "i", "o", "u", "n"), strtolower($estado->d_estado)) . ".json";
                if (file_exists($archivo)) unlink($archivo);

                if (!file_exists($archivo)) {
                    $municipios = \DB::select("SELECT DISTINCT c_municipio, d_municipio, COUNT(id_asenta_cpcons) as id_asenta, d_codigo FROM postal_codes WHERE d_asenta != 'México' AND c_estado = " . $estado->c_estado . " GROUP BY c_municipio ORDER BY id_asenta;");
                    $objM = array();

                    foreach ($municipios as $municipio) {

                        $query = sprintf("SELECT DISTINCT c.id_asenta_cpcons, c.d_tipo_asenta, c.d_asenta, c.d_codigo, c.id, CONCAT(d_tipo_asenta, ' ', d_asenta) as nom_asentamiento, IFNULL(s.title, '') as copo
                                            FROM postal_codes c
                                                LEFT JOIN copos s ON (s.id = c.c_municipio)
                                            WHERE c.d_asenta != 'México' AND c.c_estado = %s AND c.c_municipio = %s
                                            GROUP BY c.d_asenta ORDER BY c.d_asenta;", $estado->c_estado, $municipio->c_municipio);

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

                            array_push($objAsentamientos, $objA);

                            unset($objA);
                        }

                        $objM = array(
                            "id" => $municipio->c_municipio,
                            "municipio" => $municipio->d_municipio,
                            "cp" => $municipio->d_codigo,
                            "asentamientos" => $objAsentamientos
                        );

                        array_push($objMunicipios, $objM);

                        unset($objM);
                    }

                    $obj = array(
                        "id" => $estado->c_estado,
                        "estado" => $estado->d_estado,
                        "cp" => 0,
                        "municipios" => $objMunicipios
                    );

                    array_push($objEstados, $obj);
                    unset($obj);

                    echo "Estado " . $estado->d_estado . " agregado al array con sus municipios y asentamientos\n";

                    $json = json_encode($objEstados, JSON_UNESCAPED_UNICODE);
                    file_put_contents($archivo, $json);
                }
                else{
                    echo "El archivo " . $archivo ." ya existe\n";
                }
            }

            echo "Se crearon las Estados, Municipios y Asentamientos Json\n";
        } catch (Exception $ex) {
            \Log::error($ex->getMessage());
            throw new Exception($ex->getMessage());
        }
    }

    private function TaggearNotasEstadoMunicipioAsentamiento()
    {
        try{
            BuscarPalabraTitulo::dispatch();
            //BuscarPalabraResena::dispatch();
            //BuscarPalabraContenido::dispatch();
        }
        catch(Exception $ex){
            \Log::error($ex->getMessage());
            throw new Exception($ex->getMessage());
        }
    }

}
