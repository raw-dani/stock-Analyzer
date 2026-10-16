<?php

declare(strict_types=1);

namespace app\models\form;

use app\models\User;
use Yii;
use yii\base\Model;

/**
 * Form untuk mengedit profil pengguna dan reset/ubah password.
 */
final class ProfileForm extends Model
{
    public ?string $username = null;
    public ?string $email = null;
    public ?string $old_password = null;
    public ?string $new_password = null;
    public ?string $new_password_repeat = null;

    private User $_user;

    public function __construct(User $user, array $config = [])
    {
        $this->_user = $user;
        $this->username = $user->username;
        $this->email = $user->email;
        parent::__construct($config);
    }

    public function rules(): array
    {
        return [
            [['username', 'email'], 'required'],
            [['username'], 'string', 'min' => 3, 'max' => 64],
            [['email'], 'email'],
            // Validasi unik kecuali milik user saat ini
            ['username', function ($attribute) {
                $exists = User::find()
                    ->where(['username' => $this->username])
                    ->andWhere(['!=', 'id', $this->_user->id])
                    ->exists();
                if ($exists) {
                    $this->addError($attribute, 'Username ini sudah digunakan oleh akun lain.');
                }
            }],
            ['email', function ($attribute) {
                $exists = User::find()
                    ->where(['email' => $this->email])
                    ->andWhere(['!=', 'id', $this->_user->id])
                    ->exists();
                if ($exists) {
                    $this->addError($attribute, 'Email ini sudah digunakan oleh akun lain.');
                }
            }],
            [['old_password', 'new_password', 'new_password_repeat'], 'string'],
            [['new_password'], 'string', 'min' => 6],
            [['new_password_repeat'], 'compare', 'compareAttribute' => 'new_password', 'message' => 'Konfirmasi password baru tidak cocok.'],
            // Jika ingin ganti password, wajib verifikasi password lama
            ['old_password', function ($attribute) {
                if (!empty($this->new_password)) {
                    if (empty($this->old_password)) {
                        $this->addError($attribute, 'Password lama harus diisi untuk mengubah password.');
                    } elseif (!$this->_user->validatePassword($this->old_password)) {
                        $this->addError($attribute, 'Password lama tidak sesuai.');
                    }
                }
            }],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'username' => 'Username',
            'email' => 'Alamat Email',
            'old_password' => 'Password Lama',
            'new_password' => 'Password Baru',
            'new_password_repeat' => 'Ulangi Password Baru',
        ];
    }

    public function save(): bool
    {
        if (!$this->validate()) {
            return false;
        }

        $this->_user->username = $this->username;
        $this->_user->email = $this->email;

        if (!empty($this->new_password)) {
            $this->_user->setPassword($this->new_password);
            $this->_user->generateAuthKey();
        }

        return $this->_user->save();
    }
}
