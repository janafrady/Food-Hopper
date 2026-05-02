<?php
// auth.php  –  Session helpers

if (session_status() === PHP_SESSION_NONE) session_start();

function loginCustomer(int $id, string $name): void {
    $_SESSION['user_type'] = 'customer';
    $_SESSION['user_id']   = $id;
    $_SESSION['user_name'] = $name;
}

function loginRestaurant(int $id, string $name): void {
    $_SESSION['user_type'] = 'restaurant';
    $_SESSION['user_id']   = $id;
    $_SESSION['user_name'] = $name;
}

function loginContractor(int $id, string $name): void {
    $_SESSION['user_type'] = 'contractor';
    $_SESSION['user_id']   = $id;
    $_SESSION['user_name'] = $name;
}

function logout(): void {
    session_destroy();
}

function currentUser(): ?array {
    if (!empty($_SESSION['user_id'])) {
        return [
            'id'   => $_SESSION['user_id'],
            'name' => $_SESSION['user_name'],
            'type' => $_SESSION['user_type'],
        ];
    }
    return null;
}

function requireLogin(string $type = ''): array {
    $u = currentUser();
    if (!$u) {
        header('Location: ../index.php?error=login_required');
        exit;
    }
    if ($type && $u['type'] !== $type) {
        header('Location: ../index.php?error=unauthorized');
        exit;
    }
    return $u;
}
