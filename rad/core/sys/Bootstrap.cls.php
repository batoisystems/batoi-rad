<?php
namespace Core\Sys;

class Bootstrap {
    public function __construct() {
    }
    
    /**
     * Generate alert block based on alert type and message.
     */
    public static function generateAlert($message, $type) {
        $alert = '';
        if ($type != '') {
            $alert = '<div class="alert alert-'.$type.'">';
            switch($type){
                case 'success':
                    $alert .= '<i class="bi bi-check-circle-fill"></i> ';
                    break;
                case 'danger':
                    $alert .= '<i class="bi bi-exclamation-circle-fill"></i> ';
                    break;
                case 'info':
                    $alert .= '<i class="bi bi-exclamation-circle-fill"></i> ';
                    break;
                case 'warning':
                    $alert .= '<i class="bi bi-exclamation-circle-fill"></i> ';
                    break;
                // Add more cases for other alert types as needed
                default:
                    break;
            }
            $alert .= $message;
            $alert .= '</div>';
        }
        return $alert;
    }
    
    /**
     * Generates a navbar with the provided items
     *
     * @param array $items
     * @return string
     */
    public static function generateNavbar(array $items) {
        $navbar = '<nav class="navbar navbar-expand-lg navbar-light bg-light">';
        foreach ($items as $item) {
            $navbar .= '<a class="nav-link" href="' . $item['href'] . '">' . $item['text'] . '</a>';
        }
        $navbar .= '</nav>';

        return $navbar;
    }

    /**
     * Generates a card with the provided title and content
     *
     * @param string $title
     * @param string $content
     * @return string
     */
    public function generateCard(string $title, string $content) {
        // Encode output to prevent XSS attacks
        $title = htmlspecialchars($title);
        $content = htmlspecialchars($content);

        return "
            <div class='card'>
                <div class='card-header'>$title</div>
                <div class='card-body'>$content</div>
            </div>
        ";
    }
}
