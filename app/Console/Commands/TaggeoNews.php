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
            //$this->CraerNoticias();
            //$this->CrearEstadosMunicipios();
            $this->BuscarNota();

            echo "Proceso Terminado\n";
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
                //unlink($archivo);
                //echo "Se elimino el archivo ".$archivo."\n";
                echo "El archivo ".$archivo." ya existe\n";

                return true;
            }

            $news = \DB::select("SELECT id, title, summary, content FROM news WHERE created_at >= now() - INTERVAL 1 DAY AND id_author != 100  AND id_status_news = 2 AND url IS NULL AND content <> '' ORDER BY created_at DESC");

            foreach ($news as $new) {
                $obj = array(
                    "id" => $new->id,
                    "title" => strtolower($new->title),
                    "summary" => strtolower(str_replace(array("\n", "\t", "\\"), "", $new->summary)),
                    "content" => strip_tags(strtolower(str_replace(array("\n", "\t", "\\"), "", $new->content))),
                    "estado" => 0,
                    "municipio" => 0,
                    "asentamiento" => 0,
                    "cp" => 0,
                    "idPostalCode" => 0
                );

                array_push($objNews, $obj);

                unset($obj);
            }

            $json = json_encode($objNews, JSON_UNESCAPED_UNICODE);
            file_put_contents($archivo, $json);

            unset($objNews);

            echo "Se creo el archivo ".$archivo."\n";

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

            $estados = \DB::select("SELECT DISTINCT c_estado, d_estado, COUNT(c_municipio) FROM postal_codes WHERE d_asenta != 'México' GROUP BY d_estado ORDER BY c_municipio;");
            foreach ($estados as $estado) {

                $objEstados = array();
                $objMunicipios = array();
                $objAsentamientos = array();

                $archivo = "public/json/" . str_replace(array(" ", "á", "é", "í", "ó", "ú", "ñ"), array("-", "a", "e", "i", "o", "u", "n"), strtolower($estado->d_estado)) . ".json";
                if (!file_exists($archivo)) {
                    //unlink($archivo);

                    $municipios = \DB::select("SELECT DISTINCT c_municipio, d_municipio, COUNT(id_asenta_cpcons) as id_asenta FROM postal_codes WHERE d_asenta != 'México' AND c_estado = " . $estado->c_estado . " GROUP BY c_municipio ORDER BY id_asenta;");
                    $objM = array();

                    foreach ($municipios as $municipio) {

                        $asentamientos = \DB::select("SELECT DISTINCT id_asenta_cpcons, d_tipo_asenta, d_asenta, d_codigo, id, CONCAT(d_tipo_asenta, ' ', d_asenta) as nom_asentamiento FROM postal_codes WHERE d_asenta != 'México' AND c_estado = " . $estado->c_estado . " AND c_municipio = " . $municipio->c_municipio . " GROUP BY d_asenta ORDER BY d_asenta;");
                        $objA = array();
                        foreach ($asentamientos as $asentamiento) {
                            $objA = array(
                                "id" => $asentamiento->id_asenta_cpcons,
                                "nom_asentamiento" => $asentamiento->nom_asentamiento,
                                "d_tipo_asenta" => $asentamiento->d_tipo_asenta,
                                "d_asenta" => $asentamiento->d_asenta,
                                "cp" => $asentamiento->d_codigo,
                                "idPostalCode" => $asentamiento->id
                            );

                            array_push($objAsentamientos, $objA);

                            unset($objA);

                            /*
                            $ar1 = str_replace(array(" ", "á", "é", "í", "ó", "ú", "ñ"), array("-", "a", "e", "i", "o", "u", "n"), strtolower($estado->d_estado));
                            $ar2 = str_replace(array(" ", "á", "é", "í", "ó", "ú", "ñ"), array("-", "a", "e", "i", "o", "u", "n"), strtolower($municipio->d_municipio));
                            $ar3 = str_replace(array(" ", "/", "á", "é", "í", "ó", "ú", "ñ"), array("-", "-", "a", "e", "i", "o", "u", "n"), strtolower($asentamiento->nom_asentamiento));

                            $asentamientoFile = sprintf("public/json/%s-%s-%s.json", $ar1, $ar2, $ar3);

                            $json = json_encode($objAsentamientos, JSON_UNESCAPED_UNICODE);
                            file_put_contents($asentamientoFile, $json);
                            */
                        }

                        $objM = array(
                            "id" => $municipio->c_municipio,
                            "municipio" => $municipio->d_municipio,
                            "asentamientos" => $objAsentamientos
                        );

                        array_push($objMunicipios, $objM);

                        unset($objM);
                    }

                    $obj = array(
                        "id" => $estado->c_estado,
                        "estado" => $estado->d_estado,
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

    private function BuscarNota()
    {
        try{
            BuscarPalabraContenido::dispatch();
        }
        catch(Exception $ex){
            \Log::error($ex->getMessage());
            throw new Exception($ex->getMessage());
        }
    }
}
