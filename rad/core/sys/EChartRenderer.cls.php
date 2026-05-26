<?php
namespace Core\Sys;

class EChartRenderer {
    public function render(array $data) {
        // code to generate EChart using $data
    }

    /**
     * Prepares data for an ECharts visualization
     *
     * @param array $data The data for the report
     * @param array $xKey The key(s) for the x-axis data
     * @param array $yKeys The key(s) for the y-axis data
     * @return array The data for the ECharts visualization
     */
    public function prepareChart($data, $xKey, $yKeys) {
        $preparedData = ['xAxis' => [], 'series' => []];
    
        // Initialize an empty array for each series
        foreach ($yKeys as $yKey) {
            if (!empty($yKey)) { // Check if the yKey is not empty
                $preparedData['series'][$yKey] = ['name' => $yKey, 'type' => 'bar', 'data' => []];
            }
        }
    
        foreach ($data as $row) {
            $preparedData['xAxis'][] = $row[$xKey];
            foreach ($yKeys as $yKey) {
                if (!empty($yKey) && isset($row[$yKey]) && !is_null($row[$yKey])) { // Check if the yKey is not empty and the data exists and is not null
                    $preparedData['series'][$yKey]['data'][] = intval($row[$yKey]); // Convert the data to an integer
                }
            }
        }
    
        // Convert series from associative array to indexed array
        $preparedData['series'] = array_values($preparedData['series']);
    
        return $preparedData;
    }    

    /**
     * Renders a report as an ECharts visualization
     *
     * @param array $data The data for the report
     * @return string The JavaScript for the ECharts visualization
     */
    public function renderChart($data) {
        $option = [
            'xAxis' => [
                'type' => 'category',
                'data' => $data['xAxis'],
            ],
            'yAxis' => [
                'type' => 'value'
            ],
            'series' => $data['series'],
        ];
        $js = 'var chart = echarts.init(document.getElementById("main"));';
        $js .= 'var option = ' . json_encode($option) . ';';
        $js .= 'chart.setOption(option);';
    
        return $js;
    }    
        
}
