<?php
session_start();

class Session {
    public static function set($key, $value) {
        $_SESSION[$key] = $value;
    }
    
    public static function get($key) {
        return isset($_SESSION[$key]) ? $_SESSION[$key] : null;
    }
    
    public static function has($key) {
        return isset($_SESSION[$key]);
    }
    
    public static function remove($key) {
        unset($_SESSION[$key]);
    }
    
    public static function destroy() {
        session_destroy();
    }
    
    public static function regenerate() {
        session_regenerate_id(true);
    }
    
    public static function setFlash($key, $message) {
        $_SESSION['flash_' . $key] = $message;
    }
    
    public static function getFlash($key) {
        $message = isset($_SESSION['flash_' . $key]) ? $_SESSION['flash_' . $key] : null;
        if ($message) {
            unset($_SESSION['flash_' . $key]);
        }
        return $message;
    }
}
?>