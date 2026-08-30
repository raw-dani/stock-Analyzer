<?php

/** @var yii\web\View $this */
/** @var app\models\form\AlertForm $model */

use yii\bootstrap5\Html;

$this->title = 'Buat Alert';
$this->params['breadcrumbs'][] = ['label' => 'Alerts', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>

<h1 class="h3 mb-3"><?= Html::encode($this->title) ?></h1>
<div class="card"><div class="card-body">
    <?= $this->render('_form', ['model' => $model]) ?>
</div></div>
