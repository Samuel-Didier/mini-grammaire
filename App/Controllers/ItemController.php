<?php

namespace App\Controllers;
use Base;
use DB;
use Template;

class ItemController
{
    protected $db;

    function __construct()
    {
        $f3 = Base::instance();

        $dsn = $f3->get('db_dns');
        $user = $f3->get('db_user');
        $pass = $f3->get('db_pass');

        try {
            $this->db = new DB\SQL($dsn, $user, $pass);
        } catch (\PDOException $e) {
            // Attempt to create database if it doesn't exist
            if (strpos($e->getMessage(), 'Unknown database') !== false) {
                // Extract host from DSN
                preg_match('/host=([^;]+)/', $dsn, $matches);
                $host = $matches[1] ?? 'localhost';

                try {
                    $pdo = new PDO("mysql:host=$host", $user, $pass);
                    $pdo->exec("CREATE DATABASE IF NOT EXISTS shopping_list");
                    $this->db = new DB\SQL($dsn, $user, $pass);
                } catch (\PDOException $ex) {
                    die("Erreur de connexion : " . $ex->getMessage());
                }
            } else {
                die("Erreur de connexion : " . $e->getMessage());
            }
        }

        // Create table if not exists
        $this->db->exec(
            "CREATE TABLE IF NOT EXISTS items (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                quantity INT DEFAULT 1,
                price DECIMAL(10, 2) DEFAULT 0.00,
                checked TINYINT(1) DEFAULT 0
            )"
        );
    }

    function index($f3)
    {
        $items = $this->db->exec('SELECT * FROM items ORDER BY checked ASC, id DESC');

        $total = 0;
        foreach ($items as $item) {
            $total += $item['price'] * $item['quantity'];
        }

        $f3->set('items', $items);
        $f3->set('total', $total);
        echo Template::instance()->render('layout.html');
    }

    function add($f3)
    {
        $name = $f3->get('POST.name');
        $quantity = $f3->get('POST.quantity');
        $price = $f3->get('POST.price');

        if ($name) {
            $this->db->exec(
                'INSERT INTO items (name, quantity, price) VALUES (?, ?, ?)',
                array($name, $quantity, $price)
            );
        }
        $f3->reroute('/');
    }

    function delete($f3, $params)
    {
        $this->db->exec('DELETE FROM items WHERE id = ?', $params['id']);
        $f3->reroute('/');
    }

    function update($f3, $params)
    {
        $id = $params['id'];
        // Check if 'checked' is present in POST data
        $checked = $f3->exists('POST.checked') ? 1 : 0;

        $this->db->exec('UPDATE items SET checked = ? WHERE id = ?', array($checked, $id));
        $f3->reroute('/');
    }
}