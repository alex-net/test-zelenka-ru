<?php

namespace app\models;

use Yii;

class Order extends \yii\base\Model implements ViewAsJsonInterface
{
    public $id, $real_id, $client_id, $warehouse_id;
    public $created, $updated, $status, $type, $paid, $promocode;

    public $purchasesItems = [];

    private $isExists;

    public function init()
    {
        $this->isExists = $this->id &&  Yii::$app->db->createCommand('select id from {{%orders}} where id = :id', [':id' => $this->id])->queryScalar();
        parent::init();

        if ($this->isExists) {
            // склады
            $this->warehouse_id = Yii::$app->db->createCommand('select warehouse_id from {{%warehouses}} where oid = :id', [':id' => $this->id])->queryColumn();
            // элементы заказа ...
            $this->purchasesItems = Yii::$app->db->createCommand('select pid, qty, amount from {{%purchases}} where oid = :id', [':id' => $this->id])->queryAll();
        }

    }

    public function rules()
    {
        return [
            [['id', 'real_id'], 'integer', 'min' => 0,],
            ['warehouse_id', 'filter', 'filter' => [$this, 'warehouseSplitter']],
            ['warehouse_id', 'each', 'rule' => ['integer', 'min' => 0, 'max' => 65535]],
            [['status','type'], 'integer'],
            ['paid', 'boolean'],
            ['created', 'default', 'value' => date('Y-m-d H:i:s')],
            [['created', 'updated'], 'datetime', 'format' => 'php:Y-m-d H:i:s'],
            ['promocode', 'string', 'max' => 15],
            ['purchasesItems', 'required'],
            ['client_id', 'checClient'],
        ];
    }


    /**
     * преобразование телефонных номеров
     *
     * @param      string  $val    Номер телефона  в текстовом представлении
     */
    public function warehouseSplitter($val)
    {
        if (!$val) {
            return [];
        }
        $dafa = is_array($val) ? $val : preg_split('#\s*,\s*#', $val);
        return $dafa;
    }

    /**
     * проерка клиента ..
     */
    public function checClient($attr)
    {
        $cli = Client::findById($this->$attr);
        if (!$cli) {
            $this->addErrror($attr, 'Клиент не найден');
        }
    }

    public static function findById(int $id)
    {
        $res = Yii::$app->db->createCommand('select * from {{%orders}} where id = :id', [':id' => $id])->queryOne();
        if ($res) {
            return new static($res);
        }
    }

    public function attributeLabels()
    {
        return [
            'real_id' => 'реальный ID',
            'client_id' => 'Клиент',
            'warehouse_id' => 'Номер склада',
            'created' => 'Создан',
            'updated' => 'Обновлён',
            'status' => 'Статус заказа',
            'type' => 'Тип заказа',
            'paid' => 'Оплачен',
            'promocode' => 'Промокод',
        ];
    }

    /**
     * Сохрвнение данныъ объекта....
     *
     * @return     bool  Результатт сохранения
     */
    public function save()
    {
        if (!$this->validate()) {
            return false;
        }

        $attrs = $this->getAttributes($this->activeAttributes(), ['id', 'purchasesItems', 'warehouse_id']);
        if ($this->isExists) {
            $attrs['updated'] = date('Y-m-d H:i:s');
            Yii::$app->db->createCommand()->update('{{%orders}}', $attrs, ['id' => $this->id])->execute();
            Yii::$app->db->createCommand()->delete('{{%purchases}}', ['oid' => $this->id])->execute();
            Yii::$app->db->createCommand()->delete('{{%warehouses}}', ['oid' => $this->id])->execute();
        } else {
            if ($this->id) {
                $attrs['id'] = $this->id;
            }
            $attrs['updated'] = $attrs['created'];
            Yii::$app->db->createCommand()->insert('{{%orders}}', $attrs)->execute();
            $this->id = $this->id ?: Yii::$app->db->lastInsertID;
            $this->isExists = true;
        }

        // обновляем связки ...
        $warehouses = [];
        foreach ($this->warehouse_id as $id) {
            $warehouses[] = [
                'oid' => $this->id,
                'warehouse_id' => $id,
            ];
        }
        if ($warehouses) {
            Yii::$app->db->createCommand()->batchInsert('{{%warehouses}}', array_keys($warehouses[0]), $warehouses)->execute();
        }

        $purchases = [];
        foreach ($this->purchasesItems as $item) {
            $purchases[] = [
                'oid' => $this->id,
                'pid' => $item['pid'],
                'qty' => $item['qty'],
                'amount' => $item['amount'],
            ];
        }
        if ($purchases) {
            Yii::$app->db->createCommand()->batchInsert('{{%purchases}}', array_keys($purchases[0]), $purchases)->execute();
        }

        return true;
    }

    /**
     * выдать даннные объекта в виде массив или json с подтянутыми заависимостями
     *
     * @param      bool    $toJson  Нужно ли преобразовывать резкльтат в json
     *
     * @return     array|string  данные объекта
     */
    public function viewAsJson(bool $toJson = false)
    {
        $data = $this->attributes;
        // клиент ...
        if ($data['client_id']) {
            $cli = Client::findById($data['client_id']);
            if ($cli) {
                $data['client'] = $cli->attributes;
                unset($data['client_id']);
            }
        }
        // закупки ...
        if ($this->purchasesItems) {
            $productIds = array_keys(\yii\helpers\ArrayHelper::index($this->purchasesItems, 'pid'));
            $products = Products::getById($productIds);
            foreach ($this->purchasesItems as $i => $o) {
                $data['purchasesItems'][$i]['product'] = $products[$this->purchasesItems[$i]['pid']]->viewAsJson(false);
            }
        }

        \yii\helpers\Json::$prettyPrint = true;
        return \yii\helpers\Json::encode($data);
    }

}