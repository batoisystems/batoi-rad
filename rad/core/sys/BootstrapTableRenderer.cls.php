<?php
namespace Core\Sys;

class BootstrapTableRenderer {
    public function render(array $data) {
        if (empty($data)) {
            return '<p>No data to display.</p>';
        }
        else {
            return $this->renderTable($data);
        }
    }

    /**
     * Renders a table
     *
     * @param array $data The data for the table
     * @return string The HTML for the table
     */
    private function renderTable($data) {
        $html = '<table data-toggle="table">';

        // Headers
        $html .= '<thead><tr>';
        foreach ($data[0] as $key => $value) {
            $html .= "<th>{$key}</th>";
        }
        $html .= '</tr></thead>';

        // Data
        $html .= '<tbody>';
        foreach ($data as $row) {
            $html .= '<tr>';
            foreach ($row as $cell) {
                $cell = is_null($cell) ? '' : $cell;
                $html .= "<td>" . htmlspecialchars($cell) . "</td>";
            }                        
            $html .= '</tr>';
        }
        $html .= '</tbody>';

        $html .= '</table>';

        return $html;
    }
}
