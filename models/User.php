<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveRecord;
use yii\behaviors\TimestampBehavior;
use yii\web\IdentityInterface;

/**
 * User identity model (tabel {{%user}}).
 *
 * @property int $id
 * @property string $username
 * @property string $email
 * @property string $password_hash
 * @property string $auth_key
 * @property string|null $access_token
 * @property int $status
 * @property string $role
 */
class User extends ActiveRecord implements IdentityInterface
{
    public const STATUS_DELETED = 0;
    public const STATUS_ACTIVE = 10;

    // RBAC sederhana (Modul 14.2)
    public const ROLE_ADMIN = 'admin';
    public const ROLE_USER = 'user';
    public const ROLES = [self::ROLE_ADMIN => 'Admin', self::ROLE_USER => 'User'];

    public static function tableName(): string
    {
        return '{{%user}}';
    }

    public function behaviors(): array
    {
        return [TimestampBehavior::class];
    }

    public function rules(): array
    {
        return [
            [['username', 'email', 'password_hash', 'auth_key'], 'required'],
            [['username'], 'string', 'max' => 64],
            [['username'], 'unique'],
            [['email'], 'email'],
            [['email'], 'unique'],
            [['status'], 'default', 'value' => self::STATUS_ACTIVE],
            [['status'], 'in', 'range' => [self::STATUS_ACTIVE, self::STATUS_DELETED]],
            [['role'], 'in', 'range' => array_keys(self::ROLES)],
            [['role'], 'default', 'value' => self::ROLE_USER],
            [['access_token'], 'string', 'max' => 64],
        ];
    }

    // ==== IdentityInterface ====

    public static function findIdentity($id): ?IdentityInterface
    {
        return static::findOne(['id' => $id, 'status' => self::STATUS_ACTIVE]);
    }

    public static function findIdentityByAccessToken($token, $type = null): ?IdentityInterface
    {
        return static::findOne(['access_token' => $token, 'status' => self::STATUS_ACTIVE]);
    }

    /**
     * Finds user by username
     */
    public static function findByUsername(string $username): ?self
    {
        return static::findOne(['username' => $username, 'status' => self::STATUS_ACTIVE]);
    }

    public function getId(): ?int
    {
        return $this->getPrimaryKey();
    }

    /**
     * Apakah user berperan admin (RBAC Modul 14.2).
     */
    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function getAuthKey(): ?string
    {
        return $this->auth_key;
    }

    public function validateAuthKey($authKey): bool
    {
        return $this->auth_key === $authKey;
    }

    // ==== Password ====

    public function validatePassword(string $password): bool
    {
        return \Yii::$app->security->validatePassword($password, $this->password_hash);
    }

    public function setPassword(string $password): void
    {
        $this->password_hash = \Yii::$app->security->generatePasswordHash($password);
    }

    public function generateAuthKey(): void
    {
        $this->auth_key = \Yii::$app->security->generateRandomString();
    }

    // ==== Relasi ====

    public function getWatchlists(): \yii\db\ActiveQuery
    {
        return $this->hasMany(Watchlist::class, ['user_id' => 'id']);
    }

    public function getAlerts(): \yii\db\ActiveQuery
    {
        return $this->hasMany(Alert::class, ['user_id' => 'id']);
    }
}

