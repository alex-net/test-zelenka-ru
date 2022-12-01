<?php

namespace app\models;

use Yii;

class Client extends \yii\base\Model
{
    public $id, $name, $phone;

    public function rules()
    {
        return [
            ['id', 'integer', 'min' => 0],
            [['name','phone'], 'prepareTextData'],
            ['name', 'string', 'max' => 200],
            ['phone','filter', 'filter' => [$this, 'clearPhone']],
            ['phone', 'string', 'max' => 11],
        ];
    }

    public function prepareTextData($attr)
    {
        $this->$attr = preg_replace('#\s+#', ' ', trim($this->$attr));
    }

    public function clearPhone($data)
    {
        return preg_replace('#\D+#', '', $data);
    }

    public function __call($name, $args)
    {
        return static::fetectMyFn($name, $args) ?? parent::__call($name, $args);
    }

    public static function __callStatic($name, $args)
    {
        return static::fetectMyFn($name, $args) ?? parent::__callStatic($name, $args);
    }

    private static function fetectMyFn($name, $args)
    {
        if (strpos($name, 'findBy') === 0 && $name != 'findByFiled') {
            $field = strtolower(substr($name, 6));

            if (in_array($field, ['id', 'phone'])) {
                $args[] = $field;
                return call_user_func_array([static::class, 'findByFiled'], $args)?? false;
            }
        }
    }



    private static function findByFiled($val, $fieldName)
    {
        $res = Yii::$app->db->createCommand("select * from {{%clients}} where $fieldName = :val", [':val' => $val])->queryOne();
        if ($res) {
            return new static($res);
        }
    }

    public function save()
    {
        if (!$this->validate()) {
            return false;
        }
        if (!$this->id && ($cli = static::findByPhone($this->phone))) {
            $this->id = $cli->id;
        }

        $attrs = $this->getAttributes($this->activeAttributes(), ['id']);
        if ($this->id) {
            Yii::$app->db->createCommand()->update('{{%clients}}', $attrs, ['id' => $this->id])->execute();
        } else {
            Yii::$app->db->createCommand()->insert('{{%clients}}', $attrs)->execute();
            $this->id = Yii::$app->db->lastInsertID;
        }

        return true;
    }


}