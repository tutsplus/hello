<?php

namespace app\controllers;

use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\filters\VerbFilter;
use app\models\LoginForm;
use app\models\ContactForm;

class SiteController extends Controller
{
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::className(),
                'only' => ['logout'],
                'rules' => [
                    [
                        'actions' => ['logout'],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'logout' => ['post'],
                ],
            ],
        ];
    }

    public function actions()
    {
        return [
            'error' => [
                'class' => 'yii\web\ErrorAction',
            ],
            'captcha' => [
                'class' => 'yii\captcha\CaptchaAction',
                'fixedVerifyCode' => YII_ENV_TEST ? 'testme' : null,
            ],
        ];
    }

    public function actionSay($target = 'World')
        {
            return $this->render('say', ['target' => $target]);
        }
        
    public function actionIndex()
    {
        return $this->render('index');
    }

    public function actionLogin()
    {
        if (!\Yii::$app->user->isGuest) {
            return $this->goHome();
        }

        $model = new LoginForm();
        if ($model->load(Yii::$app->request->post()) && $model->login()) {
            return $this->goBack();
        } else {
            return $this->render('login', [
                'model' => $model,
            ]);
        }
    }

    public function actionLogout()
    {
        Yii::$app->user->logout();

        return $this->goHome();
    }

    public function actionContact()
    {
        $model = new ContactForm();
        if ($model->load(Yii::$app->request->post()) && $model->contact(Yii::$app->params['adminEmail'])) {
            Yii::$app->session->setFlash('contactFormSubmitted');

            return $this->refresh();
        } else {
            return $this->render('contact', [
                'model' => $model,
            ]);
        }
    }

    public function actionAbout()
    {
        return $this->render('about');
    }
    
    public function onAuthSuccess($client)
        {
           $attributes = $client->getUserAttributes();

            /** @var Auth $auth */
            $auth = Auth::find()->where([
                'source' => $client->getId(),
                'source_id' => $attributes['id'],
            ])->one();

            if (Yii::$app->user->isGuest) {
                if ($auth) { // login
                    $user = $auth->user;
                    Yii::$app->user->login($user);
                } else { // signup
                    if (isset($attributes['email']) && isset($attributes['username']) && User::find()->where(['email' => $attributes['email']])->exists()) {
                        Yii::$app->getSession()->setFlash('error', [
                            Yii::t('app', "User with the same email as in {client} account already exists but isn't linked to it. Login using email first to link it.", ['client' => $client->getTitle()]),
                        ]);
                    } else {
                        $password = Yii::$app->security->generateRandomString(6);
                        $user = new User([
                            'username' => $attributes['login'],
                            'email' => $attributes['email'],
                            'password' => $password,
                        ]);
                        $user->generateAuthKey();
                        $user->generatePasswordResetToken();
                        $transaction = $user->getDb()->beginTransaction();
                        /* if ($user->save()) {
                            $auth = new Auth([
                                'user_id' => $user->id,
                                'source' => $client->getId(),
                                'source_id' => (string)$attributes['id'],
                            ]);
                            if ($auth->save()) {
                                $transaction->commit();
                                Yii::$app->user->login($user);
                            } else {
                                print_r($auth->getErrors());
                            }
                        } else {
                            print_r($user->getErrors());
                        }
                        */
                    }
                }
            } else { // user already logged in
                if (!$auth) { // add auth provider
                    $auth = new Auth([
                        'user_id' => Yii::$app->user->id,
                        'source' => $client->getId(),
                        'source_id' => $attributes['id'],
                    ]);
                    $auth->save();
                }
            }
        }    
}
