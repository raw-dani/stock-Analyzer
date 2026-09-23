<?php

declare(strict_types=1);

namespace app\models\form;

use app\models\User;
use Yii;
use yii\base\Model;

/**
 * Registration form (task 8.4).
 */
final class RegisterForm extends Model
{
    public ?string $username = null;
    public ?string $email = null;
    public ?string $password = null;

    public function rules(): array
    {
        return [
            [['username', 'email', 'password'], 'required'],
            [['username'], 'string', 'min' => 3, 'max' => 64],
            [['email'], 'email'],
            [['password'], 'string', 'min' => 6],
            [['username'], 'unique', 'targetClass' => User::class, 'message' => 'Username sudah dipakai.'],
            [['email'], 'unique', 'targetClass' => User::class, 'message' => 'Email sudah terdaftar.'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'username' => 'Username',
            'email' => 'Email',
            'password' => 'Password',
        ];
    }

    public function register(): ?User
    {
        if (!$this->validate()) {
            return null;
        }

        $user = new User();
        $user->username = $this->username;
        $user->email = $this->email;
        $user->setPassword($this->password);
        $user->generateAuthKey();
        $user->status = User::STATUS_ACTIVE;

        if ($user->save()) {
            return $user;
        }

        return null;
    }
}
