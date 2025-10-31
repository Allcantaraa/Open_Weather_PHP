<?php

$api_key = "253ad4356b7925cb71f9cdf53da9d986";
$nome_cidade = $_REQUEST["cidade"] ?? '';
$unidade = $_REQUEST['unidade'] ?? 'metric';

$clima = null;
$cidade_nome_completo = "";
$error_message = "";
$previsao_diaria_processada = [];
$hora_atual = "";


if (!empty($nome_cidade)) {
    $cidade_codificada = urlencode($nome_cidade);
    $geocodificacao_url = "http://api.openweathermap.org/geo/1.0/direct?q={$cidade_codificada},{BR}&limit=1&appid={$api_key}";

    $geo_json = @file_get_contents($geocodificacao_url);

    if ($geo_json === FALSE) {
        $error_message = "Erro ao buscar a cidade. A API pode estar fora do ar.";
    } else {
        $cidade = json_decode($geo_json, true);

        if (count($cidade) == 0) {
            $error_message = "Cidade '{$nome_cidade}' não encontrada.";
        } else {
            $lat = $cidade[0]["lat"];
            $lon = $cidade[0]["lon"];
            $cidade_nome_completo = $cidade[0]["name"] . ", " . $cidade[0]["country"];


            $url = "https://api.openweathermap.org/data/2.5/weather?lat={$lat}&lon={$lon}&units={$unidade}&lang=pt_br&appid={$api_key}";
            $url_previsao = "https://api.openweathermap.org/data/2.5/forecast?lat={$lat}&lon={$lon}&units={$unidade}&lang=pt_br&appid={$api_key}";

            $clima_json = @file_get_contents($url);
            $previsao_json = @file_get_contents($url_previsao);

            if ($clima_json === FALSE) {
                $error_message = "Erro ao buscar os dados do clima.";
            } else {
                $clima = json_decode($clima_json, true);
                $temperatura = round($clima["main"]["temp"]);
                $descricao = ucfirst($clima["weather"][0]["description"]);
                $sensacao_termica = round($clima["main"]["feels_like"]);
                $temperatura_minima = round($clima["main"]["temp_min"]);
                $temperatura_maxima = round($clima["main"]["temp_max"]);
                $humidade = $clima["main"]["humidity"];
                $velocidade_vento = $clima["wind"]["speed"] * 3.6;
                $icon_code = $clima["weather"][0]["icon"];
                $icon_url = "http://openweathermap.org/img/wn/{$icon_code}@2x.png";
                $timestamp_utc = $clima["dt"];
                $offset_segundos = $clima["timezone"];
                $offset_prefixo = $offset_segundos >= 0 ? '+' : '-';
                $offset_horas = floor(abs($offset_segundos) / 3600);
                $offset_minutos = floor((abs($offset_segundos) % 3600) / 60);
                $offset_string = sprintf('%s%02d:%02d', $offset_prefixo, $offset_horas, $offset_minutos);
                $data = new DateTime("@" . $timestamp_utc);
                $fuso_horario = new DateTimeZone($offset_string);
                $data->setTimezone($fuso_horario);
                $formatador_data = new IntlDateFormatter(
                    'pt_BR',
                    IntlDateFormatter::FULL,
                    IntlDateFormatter::NONE,
                    $data->getTimezone(),
                    IntlDateFormatter::GREGORIAN,
                    'EEEE, dd \'de\' MMMM \'de\' yyyy'
                );
                $formatador_hora = new IntlDateFormatter(
                    'pt_BR',
                    IntlDateFormatter::NONE,
                    IntlDateFormatter::SHORT,
                    $data->getTimezone(),
                    IntlDateFormatter::GREGORIAN
                );
                $data_atual = $formatador_data->format($data);

                if ($previsao_json !== FALSE) {
                    $previsao = json_decode($previsao_json, true);

                    if ($previsao && $previsao['cod'] == '200') {

                        foreach ($previsao['list'] as $item) {
                            $timestamp = $item['dt'];
                            $data_item = new DateTime("@" . $timestamp);
                            $data_item->setTimezone($fuso_horario);
                            $dia_chave = $data_item->format('Y-m-d');

                            if ($dia_chave == $data->format('Y-m-d')) {
                                continue;
                            }

                            $temp_min = $item['main']['temp_min'];
                            $temp_max = $item['main']['temp_max'];
                            $icon = $item['weather'][0]['icon'];
                            $formatador_dia_semana = new IntlDateFormatter('pt_BR', IntlDateFormatter::FULL, IntlDateFormatter::NONE, $fuso_horario, IntlDateFormatter::GREGORIAN, 'E');
                            $dia_semana = ucfirst($formatador_dia_semana->format($timestamp));
                            if (!isset($previsao_diaria_processada[$dia_chave])) {
                                $previsao_diaria_processada[$dia_chave] = [
                                    'temp_min' => $temp_min,
                                    'temp_max' => $temp_max,
                                    'dia_semana' => $dia_semana,
                                    'icon' => $icon
                                ];
                            } else {
                                if ($temp_min < $previsao_diaria_processada[$dia_chave]['temp_min']) {
                                    $previsao_diaria_processada[$dia_chave]['temp_min'] = $temp_min;
                                }
                                if ($temp_max > $previsao_diaria_processada[$dia_chave]['temp_max']) {
                                    $previsao_diaria_processada[$dia_chave]['temp_max'] = $temp_max;
                                }
                                if ($data_item->format('H') == '15') {
                                    $previsao_diaria_processada[$dia_chave]['icon'] = $icon;
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Clima</title>
    <link rel="stylesheet" href="css/weather_now.css" />
    <link rel="stylesheet" href="style.css">
    <link rel="shortcut icon" href="favicon.svg" type="image/x-icon">
</head>

<body>
    <header>
        <div class="container" style="
          display: flex;
          justify-content: space-between;
          align-items: center;
          width: 100%;
        ">
            <div class="header-left">
                <img src="favicon.svg" alt="" class="header-left-img" />
                <h1 class="header-left-title">Weather Now</h1>
            </div>
            <form action="<?php echo $_SERVER['PHP_SELF'] ?>" method="get" id="form-unidade">
                <input type="hidden" name="cidade" value="<?php echo htmlspecialchars($nome_cidade); ?>">

                <select name="unidade" id="unidade" class="header-button" onchange="this.form.submit()">
                    <option value="metric" <?php if ($unidade == 'metric')
                        echo 'selected'; ?>>Celsius</option>
                    <option value="imperial" <?php if ($unidade == 'imperial')
                        echo 'selected'; ?>>Fahrenheit</option>
                    <option value="standard" <?php if ($unidade == 'standard')
                        echo 'selected'; ?>>Kelvin</option>

                </select>
            </form>
        </div>
    </header>
    <main>
        <div class="container">
            <section class="section-search">
                <h2 class="section-search-subtitle">Como está o céu hoje?</h2>
                <div class="section-search-content">
                    <form action="<?php echo $_SERVER['PHP_SELF'] ?>" method="get" id="form-cidade" class="section-search-content">
                        <input type="hidden" name="unidade" value="<?php echo htmlspecialchars($unidade); ?>">
                        <input type="search" name="cidade" id="cidade" placeholder="Search for a place"
                            class="section-search-content-input"
                            value="<?php echo htmlspecialchars($nome_cidade); ?>" />
                        <button class="section-search-content-button" type="submit">Pesquisar</button>
                    </form>

                    <?php if (!empty($error_message)): ?>
                        <p style="color: #ffb8b8; text-align: center; margin-top: 15px;">
                            <?php echo $error_message; ?>
                        </p>
                    <?php endif; ?>

                </div>
            </section>

            <?php if ($clima): ?>
                <section class="weather-content">
                    <div class="weather-content-main">
                        <div class="weather-content-mural">
                            <div class="weather-content-mural-berlin">
                                <h3><?php echo $cidade_nome_completo; ?></h3>
                                <p><?php echo $data_atual; ?></p>
                            </div>
                            <div class="weather-content-mural-graus">
                                <img src="<?php echo $icon_url; ?>" alt="<?php echo $descricao; ?>"
                                    class="weather-content-mural-img" style="width: 60px; height: 60px;" />
                                <h3><?php echo $temperatura; ?>°</h3>
                            </div>
                        </div>
                        <div class="for-cards">
                            <div class="for-cards-status">
                                <p>Sensação térmica</p>
                                <p><?php echo $sensacao_termica; ?>°</p>
                            </div>
                            <div class="for-cards-status">
                                <p>Humidade</p>
                                <p><?php echo $humidade; ?>%</p>
                            </div>
                            <div class="for-cards-status">
                                <p>Ventos</p>
                                <p><?php echo round($velocidade_vento, 1); ?> km/h</p>
                            </div>
                        </div>
                        <div class="seven-cards">
                            <p>Previsão Diária</p>
                            <div class="seven-cards-container">
                                <?php
                                if (!empty($previsao_diaria_processada)):
                                    foreach ($previsao_diaria_processada as $dia):
                                        ?>
                                        <div class="sever-cards-prediction">
                                            <p><?php echo $dia['dia_semana']; ?></p>
                                            <img src="http://openweathermap.org/img/wn/<?php echo $dia['icon']; ?>.png" alt=""
                                                style="width: 30px; height: 30px" />
                                            <div>
                                                <p><?php echo round($dia['temp_max']); ?>°</p>
                                                <p><?php echo round($dia['temp_min']); ?>°</p>
                                            </div>
                                        </div>
                                    <?php
                                    endforeach;
                                elseif ($clima):
                                    echo "<p style='color: white; font-size: 14px;'>Não foi possível carregar a previsão.</p>";
                                endif;
                                ?>
                            </div>
                        </div>
                    </div>
                </section>

            <?php elseif (empty($error_message)): ?>
                <div style="text-align: center; color: #ccc; margin-top: 50px;">
                    <p>Comece buscando por uma cidade</p>
                </div>
            <?php endif; ?>
            
        </div>
    </main>
</body>

</html>