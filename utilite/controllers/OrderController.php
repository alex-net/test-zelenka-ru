<?php

namespace app\controllers;

use yii\validators\UrlValidator;
use yii\helpers\Console;
use pcrov\JsonReader\JsonReader;

use app\models\OrderParser;

/**
 * Работа с импортом и просмоотром заказов
 */
class OrderController extends \yii\console\Controller
{
    /**
     * Обновление/добавление заказов разнами вариантами .. из локального файло или по ссылке в инете..
     *
     * @param      <type>  $urlPath  Путь к локальному файлу или сссылка на файл в интернете ..
     */
    public function actionUpdate($urlPath)
    {
        \yii\base\Event::on(OrderParser::class, OrderParser::EVENT_PROCESS, function($e) {
            $this->stdout('.');
        });

        $parser = new OrderParser(['jsonPath' => $urlPath]);
        try {
            if ($parser->parse()) {
                $this->stdout("файл импортирован успешно. \n", Console::FG_CYAN);
                return static::EXIT_CODE_NORMAL;
            }
        } catch (\Exception $e) {
            $this->stderr("пробемы с импортом (Что-то пошло не так )\n", Console::FG_RED);
            $this->stderr($e->getMessage() . "\n",  Console::FG_RED);
        }

        return static::EXIT_CODE_ERROR;
    }


    /**
     * просммотр заказа .. со всеми зависимостями ...
     *
     * @param      int     $orderId  Номер заказа в системе ....
     */
    public function actionInfo(int $orderId)
    {
        $order = \app\models\Order::findById($orderId);;
        if (!$order) {
            $this->stderr("Заказ №$orderId не найден. \n", Console::FG_RED);
            return static::EXIT_CODE_ERROR;
        }
        $this->stdout("Данные заказа №$orderId:\n", Console::FG_CYAN);
        $this->stdout($order->viewAsJson());

        return static::EXIT_CODE_NORMAL;

    }
}