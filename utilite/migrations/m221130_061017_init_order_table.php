<?php

use yii\db\Migration;

/**
 * Class m221130_061017_init_order_table
 */
class m221130_061017_init_order_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('{{%clients}}', [
            'id' => $this->primaryKey()->comment('Ключик'),
            'name' => $this->string(200)->notNull()->comment('Имя клиента'),
            'phone' => $this->string(11)->comment('Номер телефона'),
        ]);
        $this->addCommentOnTable('{{%clients}}', 'Таблица клиентов');
        $this->createIndex('client_name_ind', '{{%clients}}', ['name']);
        $this->createIndex('client_phone_ind', '{{%clients}}', ['phone'], true);


        $this->createTable('{{%products}}', [
            'id' => $this->primaryKey()->comment('Ключик'),
            'name' => $this->string(200)->notNull()->comment('Название препарата'),
            'manufacturer' => $this->string(120)->comment('Производитель'),
            'price' => $this->decimal(8, 2)->notNull()->comment('Ценник за единицу'),
        ]);
        $this->addCommentOnTable('{{%products}}', 'Таблица товаров');
        $this->createIndex('product_name_ind', '{{%products}}', ['name']);


        $this->createTable('{{%barcodes}}', [
            'id' => $this->primaryKey()->notNull()->comment('Ключик'),
            'code' => $this->string(13)->notNull()->comment('Штрих код'),
        ]);
        $this->addCommentOnTable('{{%products}}', 'Таблица штрихкодов товаров');

        $this->createIndex('barcodes_ind', '{{%barcodes}}', ['code'], true);

        $this->createTable('{{%product_barcodes}}', [
            'pid' => $this->integer()->notNull()->comment('ссылка на товар'),
            'bid' => $this->integer()->notNull()->comment('ссылка на штрих-код'),
        ]);
        $this->addCommentOnTable('{{%product_barcodes}}', 'Таблица связка штрихкод - товар');
        foreach (['pid' => 'products', 'bid' => 'barcodes'] as $field => $tbl) {
            $this->addForeignKey('fk_pb_' . $field, '{{%product_barcodes}}', [$field], $tbl, ['id'], 'cascade', 'cascade');
            $this->createIndex('ind_pb_' . $field, '{{%product_barcodes}}', $field);
        }


        $this->createTable('{{%orders}}', [
            'id' => $this->primaryKey()->comment('ключик'),
            'real_id'=> $this->integer()->unsigned()->comment('реальный ID'),
            'client_id' => $this->integer()->comment('Клиент'),
            //'warehouse_id'=> $this->smallInteger()->notNull()->unsigned()->comment('Номер склада'),
            'created' => $this->timestamp()->notNull()->comment('Создан'),
            'updated'=> $this->timestamp()->notNull()->comment('Создан'),
            'status' => $this->tinyInteger()->unsigned()->comment('Статус заказа'),
            'type' => $this->tinyInteger()->unsigned()->comment('Тип заказа'),
            'paid' => $this->boolean()->defaultValue(false)->notNull()->comment('Оплачен'),
            'promocode' => $this->string(15)->comment('Промокод'),
        ]);
        $this->addCommentOnTable('{{%orders}}', 'Таблица заказов');
        foreach (['real_id', 'client_id', 'created', 'status', 'type'] as $field) {
            $this->createIndex("orders_{$field}_ind", '{{%orders}}', [$field]);
        }

        $this->addForeignKey('fk_client', '{{%orders}}', ['client_id'], '{{%clients}}', ['id'], 'set null', 'cascade');

        $this->createTable('{{%warehouses}}', [
            'oid' => $this->integer()->notNull()->comment('Ссылка на заказ'),
            'warehouse_id' => $this->integer()->notNull()->comment('Номер склада'),
        ]);
        $this->addCommentOnTable('{{%warehouses}}', 'Перемещение заказа по складам');
        $this->addForeignKey('fk-order-warehouses', '{{%warehouses}}', ['oid'], '{{%orders}}', ['id'], 'cascade', 'cascade');
        $this->createIndex('ind-warehouses_id', '{{%warehouses}}', ['warehouse_id']);

        $this->createTable('{{%purchases}}', [
            'oid' => $this->integer()->notNull()->comment('ссылка на заказа'),
            'pid' => $this->integer()->notNull()->comment('Ссылка на товар'),
            'qty' => $this->smallInteger()->notNull()->defaultValue(1)->unsigned()->comment('Количество'),
            'amount' => $this->decimal(9, 2)->notNull()->comment('Стоимость'),
        ]);
        $this->addCommentOnTable('{{%purchases}}', 'Товары вошедшие в заказ (покупки)');
        foreach (['oid', 'pid',] as $field) {
            $this->createIndex("purchases_{$field}_ind", '{{%purchases}}', [$field]);
        }

        $this->addForeignKey('fk_orders', '{{%purchases}}', ['oid'], '{{%orders}}', ['id'], 'cascade', 'cascade');
        $this->addForeignKey('fk_products', '{{%purchases}}', ['pid'], '{{%products}}', ['id'], 'cascade', 'cascade');

    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        foreach (['purchases', 'barcodes', 'warehouses',  'product_barcodes', 'products', 'orders', 'clients'] as $tbl) {
            $this->dropTable("{{%$tbl}}");
        }
    }
}
