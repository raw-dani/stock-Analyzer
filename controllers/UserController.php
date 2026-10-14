<?php

declare(strict_types=1);

namespace app\controllers;

use app\models\PortfolioPosition;
use app\models\User;
use Yii;
use yii\data\ActiveDataProvider;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Controller Manajemen Pengguna (User Management) — Khusus Administrator.
 */
final class UserController extends Controller
{
    public function behaviors(): array
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => ['@'],
                        'matchCallback' => function ($rule, $action) {
                            /** @var User|null $user */
                            $user = Yii::$app->user->identity;
                            return $user !== null && $user->isAdmin();
                        },
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'delete' => ['POST'],
                    'toggle-role' => ['POST'],
                    'toggle-status' => ['POST'],
                ],
            ],
        ];
    }

    /**
     * Daftar semua user di sistem.
     */
    public function actionIndex(): string
    {
        $dataProvider = new ActiveDataProvider([
            'query' => User::find()->orderBy(['id' => SORT_ASC]),
            'pagination' => [
                'pageSize' => 15,
            ],
        ]);

        return $this->render('index', [
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Tambah user baru oleh Admin.
     */
    public function actionCreate(): Response|string
    {
        $model = new User();
        $model->status = User::STATUS_ACTIVE;
        $model->role = User::ROLE_USER;

        $post = Yii::$app->request->post();
        if ($model->load($post)) {
            $rawPassword = trim((string) ($post['User']['raw_password'] ?? ''));
            if (empty($rawPassword)) {
                $model->addError('password_hash', 'Password tidak boleh kosong.');
            } else {
                $model->setPassword($rawPassword);
                $model->generateAuthKey();
                if ($model->save()) {
                    Yii::$app->session->setFlash('success', "User '{$model->username}' berhasil ditambahkan.");
                    return $this->redirect(['index']);
                }
            }
        }

        return $this->render('create', [
            'model' => $model,
        ]);
    }

    /**
     * Edit data user & role / reset password oleh Admin.
     */
    public function actionUpdate(int $id): Response|string
    {
        $model = $this->findModel($id);
        $post = Yii::$app->request->post();

        if ($model->load($post)) {
            $rawPassword = trim((string) ($post['User']['raw_password'] ?? ''));
            if (!empty($rawPassword)) {
                $model->setPassword($rawPassword);
            }
            if ($model->save()) {
                Yii::$app->session->setFlash('success', "Data user '{$model->username}' berhasil diperbarui.");
                return $this->redirect(['index']);
            }
        }

        return $this->render('update', [
            'model' => $model,
        ]);
    }

    /**
     * Ganti role cepat (admin <-> user).
     */
    public function actionToggleRole(int $id): Response
    {
        $model = $this->findModel($id);
        if ($model->id === (int) Yii::$app->user->id && $model->isAdmin()) {
            Yii::$app->session->setFlash('danger', 'Anda tidak dapat menurunkan role akun admin Anda sendiri.');
            return $this->redirect(['index']);
        }

        $model->role = $model->role === User::ROLE_ADMIN ? User::ROLE_USER : User::ROLE_ADMIN;
        if ($model->save(false)) {
            Yii::$app->session->setFlash('success', "Role user '{$model->username}' diubah menjadi {$model->role}.");
        }

        return $this->redirect(['index']);
    }

    /**
     * Aktifkan / Nonaktifkan status akun user.
     */
    public function actionToggleStatus(int $id): Response
    {
        $model = $this->findModel($id);
        if ($model->id === (int) Yii::$app->user->id) {
            Yii::$app->session->setFlash('danger', 'Anda tidak dapat menonaktifkan akun Anda sendiri.');
            return $this->redirect(['index']);
        }

        $model->status = $model->status === User::STATUS_ACTIVE ? User::STATUS_DELETED : User::STATUS_ACTIVE;
        if ($model->save(false)) {
            $statusLabel = $model->status === User::STATUS_ACTIVE ? 'Aktif' : 'Non-aktif (Deleted)';
            Yii::$app->session->setFlash('success', "Status user '{$model->username}' diubah menjadi {$statusLabel}.");
        }

        return $this->redirect(['index']);
    }

    /**
     * Hapus akun user beserta posisi portofolio terkait.
     */
    public function actionDelete(int $id): Response
    {
        $model = $this->findModel($id);
        if ($model->id === (int) Yii::$app->user->id) {
            Yii::$app->session->setFlash('danger', 'Anda tidak dapat menghapus akun Anda sendiri.');
            return $this->redirect(['index']);
        }

        $username = $model->username;
        // Hapus data portofolio milik user tersebut
        PortfolioPosition::deleteAll(['user_id' => $model->id]);
        $model->delete();

        Yii::$app->session->setFlash('success', "User '{$username}' dan seluruh portofolionya berhasil dihapus.");
        return $this->redirect(['index']);
    }

    private function findModel(int $id): User
    {
        $model = User::findOne($id);
        if ($model === null) {
            throw new NotFoundHttpException('User tidak ditemukan.');
        }
        return $model;
    }
}
