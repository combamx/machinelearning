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
            //$this->AgregarNotasEstadoMunicipio();
        } catch (Exception $ex) {
            \Log::error($ex->getMessage());
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
            $news = $this->db->table('news')->select('id', 'content', 'title')->get();
            $count = 1;

            foreach ($news as $item) {
                echo $item->id . "\n";
                if ($item->content != "") {
                    echo $item->id . " - " . $item->title . "\n";

                    $contenido = strip_tags(strtolower(str_replace(array("\n", "\t", "\\"), "", $item->content)));
                    $contenido = str_replace(array("Á", "É", "Í", "Ó", "Ú"), array("á", "é", "í", "ó", "ú"), $contenido);
                    $contenido = str_replace($eliminar, "", $contenido);
                    $contenido = str_replace($articulos, " ", $contenido);

                    $array_contenido = explode(" ", strtolower($contenido));

                    foreach ($array_contenido as $content) {

                        if ($content != "") {

                            if ($content == "script" || $content == "function" || $content == "windowdatalayer" || $content == "window") break;

                            $this->db->table('matrix')->insert([
                                "id" => $item->id,
                                "matrix" => $content
                            ]);

                            echo $item->id . " - " . $content . "\n";
                        }
                    }

                    /*
                    if ($count == 3) {
                        exit;
                    }

                    $count++;*/
                }
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
            //$query = "INSERT INTO taggeos (news, estado, municipio, asentamiento, cp, copo, fecha) SELECT DISTINCT id as news,estado,municipio,'' as asentamiento,'' as cp,'' as copo, '" . date('Y-m-d H:m:s') . "' FROM vwRelacionNotas;";
            //$this->db->unprepared($query);

            $estados = $this->db->table('estados')->select()->orderBy('estado')->get();

            foreach ($estados as $estado) {
                echo "Buscar el asentamiento, " . $estado->asentamiento . " en el contenido\n";
                $query =  "SELECT n.id FROM news n WHERE n.content LIKE '%" . $estado->asentamiento . "%' OR n.content LIKE '%".$estado->municipio."%' OR n.content LIKE '%".$estado->estado."%' AND n.content <> '' ORDER BY n.id;";
                $resultados = $this->db->select($query);

                foreach ($resultados as $resultado) {
                    $this->db->table('taggeos')->insert([
                        "news" => $resultado->id,
                        "estado" => $estado->estado,
                        "municipio" => $estado->municipio,
                        "asentamiento" => $estado->asentamiento,
                        "cp" => $estado->cp,
                        "copo" => $estado->copo,
                        "fecha" => date('Y-m-d H:m:s')
                    ]);

                    echo "Se agrego " . $resultado->id . " con los datos sig. " . $estado->estado . ", " . $estado->municipio . ", " . $estado->asentamiento . "\n";
                }
            }
        } catch (Exception $ex) {
            throw new Exception($ex->getMessage());
        }
    }
}
