<?php

namespace app\models;

use Yii;

/**
 * Класс занимается управлением данными штрихкодов ...
 */
class Barcode extends \yii\base\Model
{
    public $id, $code;

    private $pidsList;

    public function rules()
    {
        return [
            ['id', 'integer', 'min' => 0],
            ['code', 'trim'],
            ['code', 'string', 'max' => 13],
            ['code', 'required'],
            ['code', 'uniqueBarcode'],

        ];
    }

    /**
     * получение одного или нескольких объектов ...
     *
     * @param      <type>  $ids    The identifiers
     *
     * @return     bool    ( description_of_the_return_value )
     */
    public static function geById($ids)
    {
        $isArr = is_array($ids);
        $q = new \yii\db\Query();
        $q->from('{{%barcodes}}');
        $q->where(['id' => $ids]);
        $res = $q->all();
        for ($i = 0; $i < count($res); $i++) {
            $res[$i] = new static($res[$i]);
        }
        return $isArr ? $res : reset($res);
    }

    public function uniqueBarcode($attr)
    {
        $where = ['and', ['code' => $this->$attr]];
        if ($this->id) {
            $where[] = ['not', ['id' => $this->id]];
        }
        $q = new \yii\db\Query();
        $q->from('{{%barcodes}}');
        $q->select('count(*)');
        $q->where($where);

        if ($q->scalar()) {
            $this->addErrror($attr, 'дубликат кода ' . $this->$attr);
        }
    }

    public static function findByCode(string $code)
    {
        $res = Yii::$app->db->createCommand('select * from {{%barcodes}} where code = :code', [':code' => $code])->queryOne();
        if ($res){
            return new static($res);
        }
    }

    public function save()
    {
        if (!$this->validate()) {
            return false;
        }

        if ($this->id) {
            Yii::$app->db->createCommand()->update('{{%barcodes}}', ['code' => $this->code], ['id' => $this->iid])->execute();
        } else {
            Yii::$app->db->createCommand()->insert('{{%barcodes}}', ['code' => $this->code])->execute();
            $this->id = Yii::$app->db->lastInsertID;
        }

        return true;
    }

}