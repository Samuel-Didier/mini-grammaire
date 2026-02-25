<?php


namespace App\Models;
class Astuces extends \DB\SQL\Mapper
{
//    private \DB\SQL $db;

    public function __construct(?\DB\SQL $db = null)
    {
        $this->db = $db ?: \Base::instance()->get('DB');

        parent::__construct($this->db, 'astuces');
    }

    public function getAll()
    {
        try {
            $astuces = $this->db->exec('SELECT * FROM astuces ' );
            return $astuces;
        } catch (\PDOException $e) {
            return [];

        }
    }


}