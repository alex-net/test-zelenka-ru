<?php

namespace app\models;

use Yii;

class Products extends \yii\base\Model implements ViewAsJsonInterface
{
    public $id, $name, $barcodes, $price, $manufacturer;

    private $isExists;

    public function init()
    {
        $this->isExists = $this->id && Yii::$app->db->createCommand('select id from {{%products}} where id = :id', [':id' => $this->id])->queryScalar();
        parent::init();

        if ($this->isExists) {
            $this->barcodes = Yii::$app->db->createCommand('select bid from {{%product_barcodes}} where pid = :id', [':id' => $this->id])->queryColumn();
        }
    }

    public function rules()
    {
        return [
            ['id', 'integer'],
            [['name', 'manufacturer'], 'trim'],
            ['name', 'string', 'max' => 200],
            ['price', 'double', 'min' => 0],
            ['barcodes', 'each', 'rule' => ['filter', 'filter' => [$this, 'checkBarcodes']]],
            ['barcodes', 'each', 'rule' => ['integer', 'min' => 0]],
            ['manufacturer', 'string', 'max' => 120],
        ];
    }

    /**
     * Проверка штрихкода ..
     *
     * @param      <type>      $code   The code
     *
     * @throws     \Exception  (description)
     *
     * @return     int         ( description_of_the_return_value )
     */
    public function checkBarcodes($code)
    {
        if (!$code) {
            return 0;
        }
        $barcode = Barcode::findByCode($code);
        if ($barcode) {
            return $barcode->id;
        }
        $barcode = new Barcode(['code' => $code]);
        if (!$barcode->save()) {
            throw new \Exception("Ошибка в штрих коде '$code' товар {$this->id}");
        }
        return $barcode->id;
    }


    public static function getById($ids)
    {
        $isArr = is_array($ids);
        $q = new \yii\db\Query();
        $q->from('{{%products}}');
        $q->where(['id' => $ids]);
        if (!$isArr) {
            $ids = [$ids];
        }

        $res = $q->all();
        $prods = [];
        for ($i = 0; $i < count($res); $i++) {
            $prods[$res[$i]['id']] = new static($res[$i]);
        }

        // подхватываем штрих коды ..
        $code = new \yii\db\Query();
        $code->from(['pb' => '{{%product_barcodes}}']);
        $code->leftJoin(['b' => '{{%barcodes}}'], 'b.id = pb.bid');
        $code->select(['barcodes' => 'b.code', 'id' => 'pb.pid']);
        $code->where(['pb.pid' => $ids]);
        $code = $code->all();

        return $isArr ? $prods : reset($prods);
    }

    /**
     * Сохранение данных объекта ..
     *
     * @return     bool
     */
    public function save()
    {
        if (!$this->validate()) {
            return false;
        }

        $attrs = $this->getAttributes($this->activeAttributes(), ['id', 'barcodes']);

        // проверяем наличие ... в базе
        $insert = true;
        $cmd = Yii::$app->db->createCommand('select id from {{%products}} where id = :id', [':id' => $this->id]);

        if (!$this->id || $this->id && !$cmd->queryScalar()) {
            if ($this->id) {
                $attrs['id'] = $this->id;
            }
            Yii::$app->db->createCommand()->insert('{{%products}}', $attrs)->execute();
            $this->id = Yii::$app->db->lastInsertID;
        } else {
            Yii::$app->db->createCommand()->update('{{%products}}', $attrs, ['id' => $this->id])->execute();
            Yii::$app->db->createCommand()->delete('{{%product_barcodes}}', ['pid' => $this->id])->execute();
        }

        $binds = [];

        foreach ($this->barcodes as $bid) {
            $binds[] = [
                'bid' => $bid,
                'pid' => $this->id,
            ];
        }

        if ($binds) {
            Yii::$app->db->createCommand()->batchInsert('{{%product_barcodes}}', array_keys($binds[0]), $binds)->execute();
        }

        return true;
    }

    /**
     * Выгрузка данных в формате json или в виде массива...
     *
     * @param      bool    $toJson Нужно ли преобразовывать в json
     *
     * @return     <type>  ( description_of_the_return_value )
     */
    public function viewAsJson(bool $toJson = false)
    {
        $data = $this->attributes;
        // замена штрих кодов ...
        if ($data['barcodes']) {
            $data['barcodes'] = \yii\helpers\ArrayHelper::getColumn(Barcode::geById($data['barcodes']), 'code');
        }

        if (!$toJson) {
            return $data;
        }

        \yii\helpers\Json::$prettyPrint = true;
        return \yii\helpers\Json::encode($data);
    }
}




