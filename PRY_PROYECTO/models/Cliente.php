<?php
/**
 * Modelo Cliente
 * Gestión de clientes del restaurante
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/security/password_utils.php';

class Cliente {
    private $db;
    private $table = 'clientes';
    
    public $id;
    public $nombre;
    public $apellido;
    public $email;
    public $telefono;
    public $password;
    public $fecha_registro;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Obtener todos los clientes
     */
    public function getAll() {
        $query = "SELECT id, nombre, apellido, email, telefono, fecha_registro 
                  FROM {$this->table} 
                  ORDER BY fecha_registro DESC";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtener cliente por ID
     */
    public function getById($id) {
        $query = "SELECT id, nombre, apellido, email, telefono, fecha_registro 
                  FROM {$this->table} 
                  WHERE id = ?";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtener cliente por email
     */
    public function getByEmail($email) {
        $query = "SELECT * FROM {$this->table} WHERE email = ?";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$email]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Crear nuevo cliente
     */
    public function create($data) {
        $query = "INSERT INTO {$this->table} 
                  (nombre, apellido, email, telefono, password_hash) 
                  VALUES (?, ?, ?, ?, ?)";
        
        $stmt = $this->db->prepare($query);
        
        $passwordHash = hashPasswordSeguro($data['password']);
        
        return $stmt->execute([
            $data['nombre'],
            $data['apellido'],
            $data['email'],
            $data['telefono'] ?? '',
            $passwordHash
        ]);
    }
    
    /**
     * Validar credenciales
     */
    public function validarCredenciales($email, $password) {
        $cliente = $this->getByEmail($email);
        
        if ($cliente && isset($cliente['password_hash']) && verificarPasswordSeguro($password, $cliente['password_hash'])) {
            if (!esPasswordHash($cliente['password_hash']) || requiereRehashPassword($cliente['password_hash'])) {
                $nuevoHash = hashPasswordSeguro($password);
                $upd = $this->db->prepare("UPDATE {$this->table} SET password_hash = ? WHERE id = ?");
                $upd->execute([$nuevoHash, $cliente['id']]);
            }
            // No retornar el password
            unset($cliente['password_hash']);
            return $cliente;
        }
        
        return false;
    }
    
    /**
     * Actualizar cliente
     */
    public function update($id, $data) {
        $query = "UPDATE {$this->table} 
                  SET nombre = ?, apellido = ?, email = ?, telefono = ?
                  WHERE id = ?";
        
        $stmt = $this->db->prepare($query);
        
        return $stmt->execute([
            $data['nombre'],
            $data['apellido'],
            $data['email'],
            $data['telefono'] ?? '',
            $id
        ]);
    }
    
    /**
     * Cambiar password
     */
    public function cambiarPassword($id, $newPassword) {
        $query = "UPDATE {$this->table} SET password_hash = ? WHERE id = ?";
        $stmt = $this->db->prepare($query);
        $passwordHash = hashPasswordSeguro($newPassword);
        return $stmt->execute([$passwordHash, $id]);
    }
    
    /**
     * Verificar si email existe
     */
    public function emailExiste($email, $excludeId = null) {
        $query = "SELECT COUNT(*) FROM {$this->table} WHERE email = ?";
        
        if ($excludeId) {
            $query .= " AND id != ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$email, $excludeId]);
        } else {
            $stmt = $this->db->prepare($query);
            $stmt->execute([$email]);
        }
        
        return $stmt->fetchColumn() > 0;
    }
}
?>
