<?php

require_once __DIR__ . '/../models/Client.php';

class ClientController
{
    public function index(array $args): void
    {
        $user = $args['user'];
        $query = $args['query'];

        $filters = [
            'search' => $query['search'] ?? null,
            'page'   => $query['page'] ?? 1,
            'limit'  => $query['limit'] ?? 50,
        ];

        try {
            $data = Client::findByBusiness($user['business_id'], $filters);
        } catch (\PDOException $e) {
            sendJson(200, ['success' => true, 'data' => [], 'debug' => $e->getMessage()]);
            return;
        }
        sendJson(200, ['success' => true, 'data' => $data]);
    }

    public function store(array $args): void
    {
        $user = $args['user'];
        $body = $args['body'];

        if (empty(trim($body['name'] ?? ''))) {
            sendJson(400, ['success' => false, 'message' => 'El nombre es requerido']);
        }

        $id = Client::create([
            'business_id' => $user['business_id'],
            'name'        => trim($body['name']),
            'phone'       => $body['phone'] ?? null,
            'email'       => $body['email'] ?? null,
            'notes'       => $body['notes'] ?? null,
        ]);

        $client = Client::findById($id, $user['business_id']);
        sendJson(201, ['success' => true, 'message' => 'Cliente creado', 'data' => $client]);
    }

    public function show(array $args): void
    {
        $user = $args['user'];
        $id = (int)$args['params']['id'];

        $client = Client::findById($id, $user['business_id']);
        if (!$client) {
            sendJson(404, ['success' => false, 'message' => 'Cliente no encontrado']);
        }

        sendJson(200, ['success' => true, 'data' => $client]);
    }

    public function update(array $args): void
    {
        $user = $args['user'];
        $id = (int)$args['params']['id'];
        $body = $args['body'];

        $client = Client::findById($id, $user['business_id']);
        if (!$client) {
            sendJson(404, ['success' => false, 'message' => 'Cliente no encontrado']);
        }

        Client::update($id, $body);
        $updated = Client::findById($id, $user['business_id']);
        sendJson(200, ['success' => true, 'message' => 'Cliente actualizado', 'data' => $updated]);
    }

    public function destroy(array $args): void
    {
        $user = $args['user'];
        $id = (int)$args['params']['id'];

        $client = Client::findById($id, $user['business_id']);
        if (!$client) {
            sendJson(404, ['success' => false, 'message' => 'Cliente no encontrado']);
        }

        Client::delete($id, $user['business_id']);
        sendJson(200, ['success' => true, 'message' => 'Cliente eliminado']);
    }
}
