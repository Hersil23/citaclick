<?php

require_once __DIR__ . '/../models/Service.php';

class ServiceController
{
    public function index(array $args): void
    {
        $user = $args['user'];
        $categoryId = !empty($args['query']['category_id']) ? (int)$args['query']['category_id'] : null;

        try {
            $data = Service::findByBusiness($user['business_id'], $categoryId);
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

        $required = ['name', 'duration', 'price'];
        foreach ($required as $field) {
            if (empty($body[$field]) && $body[$field] !== '0') {
                sendJson(400, [
                    'success' => false,
                    'message' => 'Nombre, duracion y precio son requeridos',
                    'errors' => [$field => 'Campo requerido'],
                ]);
            }
        }

        $id = Service::create([
            'business_id' => $user['business_id'],
            'category_id' => $body['category_id'] ?? null,
            'name'        => trim($body['name']),
            'description' => $body['description'] ?? null,
            'duration'    => (int)$body['duration'],
            'price'       => (float)$body['price'],
            'image'       => $body['image'] ?? null,
        ]);

        $service = Service::findById($id, $user['business_id']);
        sendJson(201, ['success' => true, 'message' => 'Servicio creado', 'data' => $service]);
    }

    public function show(array $args): void
    {
        $user = $args['user'];
        $id = (int)$args['params']['id'];

        $service = Service::findById($id, $user['business_id']);
        if (!$service) {
            sendJson(404, ['success' => false, 'message' => 'Servicio no encontrado']);
        }

        sendJson(200, ['success' => true, 'data' => $service]);
    }

    public function update(array $args): void
    {
        $user = $args['user'];
        $id = (int)$args['params']['id'];
        $body = $args['body'];

        $service = Service::findById($id, $user['business_id']);
        if (!$service) {
            sendJson(404, ['success' => false, 'message' => 'Servicio no encontrado']);
        }

        Service::update($id, $body);
        $updated = Service::findById($id, $user['business_id']);
        sendJson(200, ['success' => true, 'message' => 'Servicio actualizado', 'data' => $updated]);
    }

    public function destroy(array $args): void
    {
        $user = $args['user'];
        $id = (int)$args['params']['id'];

        $service = Service::findById($id, $user['business_id']);
        if (!$service) {
            sendJson(404, ['success' => false, 'message' => 'Servicio no encontrado']);
        }

        Service::delete($id);
        sendJson(200, ['success' => true, 'message' => 'Servicio eliminado']);
    }

    public function categories(array $args): void
    {
        $user = $args['user'];
        $data = Service::getCategories($user['business_id']);
        sendJson(200, ['success' => true, 'data' => $data]);
    }

    public function storeCategory(array $args): void
    {
        $user = $args['user'];
        $body = $args['body'];
        $name = trim($body['name'] ?? '');

        if (empty($name)) {
            sendJson(400, ['success' => false, 'message' => 'El nombre es requerido']);
        }

        if (Service::categoryExists($user['business_id'], $name)) {
            sendJson(409, ['success' => false, 'message' => 'Ya existe una categoria con ese nombre']);
        }

        $id = Service::createCategory($user['business_id'], $name);
        $category = Service::findCategoryById($id, $user['business_id']);
        sendJson(201, ['success' => true, 'message' => 'Categoria creada', 'data' => $category]);
    }

    public function updateCategory(array $args): void
    {
        $user = $args['user'];
        $id = (int)$args['params']['id'];
        $body = $args['body'];
        $name = trim($body['name'] ?? '');

        if (empty($name)) {
            sendJson(400, ['success' => false, 'message' => 'El nombre es requerido']);
        }

        $category = Service::findCategoryById($id, $user['business_id']);
        if (!$category) {
            sendJson(404, ['success' => false, 'message' => 'Categoria no encontrada']);
        }

        if (Service::categoryExists($user['business_id'], $name, $id)) {
            sendJson(409, ['success' => false, 'message' => 'Ya existe una categoria con ese nombre']);
        }

        Service::updateCategory($id, $user['business_id'], $name);
        $updated = Service::findCategoryById($id, $user['business_id']);
        sendJson(200, ['success' => true, 'message' => 'Categoria actualizada', 'data' => $updated]);
    }

    public function destroyCategory(array $args): void
    {
        $user = $args['user'];
        $id = (int)$args['params']['id'];

        $category = Service::findCategoryById($id, $user['business_id']);
        if (!$category) {
            sendJson(404, ['success' => false, 'message' => 'Categoria no encontrada']);
        }

        Service::deleteCategory($id, $user['business_id']);
        sendJson(200, ['success' => true, 'message' => 'Categoria eliminada']);
    }
}
