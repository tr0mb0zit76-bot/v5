<?php
// Модуль скоринга компаний
if ($user['role'] !== 'admin' && $user['role'] !== 'manager') {
    echo '<div class="alert error">У вас нет доступа к этому модулю</div>';

    return;
}
?>

<div class="content-header">
    <h1>Скоринг компаний</h1>
    <p>Проверка надежности контрагента и расчет кредитного лимита по ИНН</p>
</div>

<div class="form-container">
    <div class="form-group">
        <label for="innInput">Введите ИНН компании</label>
        <div class="input-group">
            <input type="text" 
                   id="innInput" 
                   class="form-control" 
                   placeholder="Пример: 7707083893" 
                   maxlength="12">
            <button id="checkInnBtn" class="btn btn-primary">
                <i class="fas fa-search"></i> Проверить
            </button>
        </div>
        <p class="text-muted">Введите 10 цифр для юридических лиц или 12 цифр для ИП</p>
    </div>
</div>

<div id="scoringLoading" class="loading" style="display: none;">
    <div class="spinner"></div>
    <div class="loading-text">Анализ компании...</div>
</div>

<div id="scoringResults" class="scoring-results" style="display: none;">
    <!-- Результаты будут загружены сюда -->
</div>

<!-- Подключение модуля скоринга -->
<script src="/cabinet/assets/js/scoring.js"></script>
<link rel="stylesheet" href="/cabinet/assets/css/scoring.css">