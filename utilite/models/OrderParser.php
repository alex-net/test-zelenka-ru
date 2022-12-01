<?php

namespace app\models;

use \yii\validators\UrlValidator;
use pcrov\JsonReader\JsonReader;

/**
 * Класс парсера json данных ..
 */
class OrderParser extends \yii\base\Model
{
    const EVENT_PROCESS = 'process go';

    public $jsonPath;

    private $isLocal;

    public function rules()
    {
        return [
            ['jsonPath', 'string'],
            ['jsonPath', 'required'],
            ['jsonPath', 'detectLocal'],
            ['jsonPath', 'file', 'extensions' => ['json'], 'when' => function(){
                return $this->isLocal;
            }],
        ];
    }

    public function detectLocal($attr)
    {
        $urlValidator = new \yii\validators\UrlValidator();
        $this->isLocal = !$urlValidator->validate($this->$attr);
    }

    /**
     * Сохранение однго заказа в базу
     *
     * @param      <type>      $orderData  The order data
     *
     * @throws     \Exception  (description)
     */
    private function importOneOrder($orderData)
    {
        // сохраняем клиента ..
        $cli = new Client([
            'name' => $orderData['user_name'],
            'phone' => $orderData['user_phone'],
        ]);
        if (!$cli->save()) {
            throw new \Exception("Заказ {$orderData['id']}: клиент не найден\n");
        }


        $orderData['client_id'] = $cli->id;

        // идём по товарам ..надо их тоже добавить / обновить
        if (empty($orderData['items'])) {
            throw new \Exception("Заказ {$orderData['id']}: без покупок\n");
        }
        $items = [];
        foreach ($orderData['items'] as $producData) {
            $barcodes = trim($producData['barcodes']);
            $product = new Products([
                'id' => intval($producData['id']),
                'name' => $producData['name'],
                'manufacturer'=> $producData['manufacturer'],
                'price' => $producData['price'],
                'barcodes' => $barcodes ? preg_split('#\s*,\s*#', $barcodes) : [],
            ]);
            if ($product->save()) {
                $items[] = [
                    'pid' => $product->id,
                    'qty' => floatval($producData['quantity']),
                    'amount' => $producData['amount'],
                ];
            } else {
                echo '*' . $product->id;
                print_r($product->errors);
            }
        }

        unset($orderData['user_name'], $orderData['user_phone'], $orderData['items']);
        $orderData['purchasesItems'] = $items;
        if ( $orderData['id']== 80020) {
            echo '<!>'.$orderData['id'];
        }

        // замена полей ..
        foreach(['created_at' => 'created', 'is_paid' => 'paid'] as $oldK => $newK) {
            $orderData[$newK] = $orderData[$oldK];
            unset($orderData[$oldK]);
        }

        //обновляем зказ ...
        $order = new Order($orderData);
        $order->save();

        $this->trigger(static::EVENT_PROCESS);
    }

    /**
     * разбор входных данных (json файл)
     *
     * @return     bool  ( description_of_the_return_value )
     */
    public function parse()
    {
        if (!$this->validate()) {
            return false;
        }
        $reader = new JsonReader();
        $reader->open($this->jsonPath);

        $orderData = [];
        $orderItemPosition = [];
        $orderItemPositionArr = [];
        while ($reader->read()) {
            switch ($reader->depth()) {
                // проход заказов ...
                case 2:
                  if ($orderData) {
                    $this->importOneOrder($orderData);
                  }
                  $orderData = [];
                  break;

                // поля заказа и элементы позиций
                case 3:
                    switch ($reader->type()) {
                        case JsonReader::END_ARRAY:
                            $orderData[$reader->name()] = $orderItemPositionArr;
                            $orderItemPositionArr = [];
                        case JsonReader::ARRAY:
                            break;
                        default:
                            $orderData[$reader->name()] = $reader->value();
                    }
                    break;
                // очередная позиция .. заказа
                case 4:
                    if ($orderItemPosition) {
                        $orderItemPositionArr[] = $orderItemPosition;
                        $orderItemPosition = [];
                    }
                    break;
                // поля позиции закзаза
                case 5:
                    $orderItemPosition[$reader->name()] = $reader->value();
            }
        }


        $reader->close();

        return true;
    }
}