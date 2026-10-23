<?php

declare(strict_types=1);

namespace app\widgets;

use app\models\BlogPost;
use Yii;
use yii\base\Widget;

/**
 * Widget tombol cepat posting analisis saham ke Blog Info Saham AI.
 * Khusus admin dengan pengecekan granular (symbol + time_frame + strategy_name + analysis_date).
 */
class BlogQuickPostWidget extends Widget
{
    public string $symbol = '';
    public string $stockName = '';
    public string $timeFrame = '1D';
    public string $strategyName = 'general';
    public string $category = '';
    public string $analysisDate = '';
    public string $targetElementId = '';
    public array $metrics = [];
    public string $buttonClass = 'btn btn-outline-info btn-sm shadow-sm';

    public function run(): string
    {
        // Hanya render jika user adalah Admin
        if (Yii::$app->user->isGuest) {
            return '';
        }

        $user = Yii::$app->user->identity;
        if (!$user || !method_exists($user, 'isAdmin') || !$user->isAdmin()) {
            return '';
        }

        if (empty($this->analysisDate)) {
            $this->analysisDate = date('Y-m-d');
        }

        $category = !empty($this->category) 
            ? $this->category 
            : BlogPost::strategyToCategory($this->strategyName);

        // Granular check di database
        $existingPost = BlogPost::findExisting(
            $this->symbol,
            $this->timeFrame,
            $this->strategyName,
            $this->analysisDate
        );

        return $this->render('@app/views/blog/_quick_post_button', [
            'symbol' => strtoupper($this->symbol),
            'stockName' => $this->stockName,
            'timeFrame' => strtoupper($this->timeFrame),
            'strategyName' => strtolower($this->strategyName),
            'category' => $category,
            'analysisDate' => $this->analysisDate,
            'targetElementId' => $this->targetElementId,
            'metrics' => $this->metrics,
            'existingPost' => $existingPost,
            'buttonClass' => $this->buttonClass,
            'widgetId' => $this->getId(),
        ]);
    }
}
