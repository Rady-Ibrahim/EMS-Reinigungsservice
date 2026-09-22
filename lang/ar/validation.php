<?php

return [
    'required'  => 'حقل :attribute مطلوب.',
    'email'     => 'يجب أن يكون حقل :attribute عنوان بريد إلكتروني صحيح.',
    'min'       => [
        'string' => 'يجب ألا يقل حقل :attribute عن :min أحرف.',
    ],
    'max'       => [
        'string' => 'يجب ألا يتجاوز حقل :attribute :max أحرف.',
    ],
    'in'        => 'القيمة المحددة لـ :attribute غير صالحة.',
    'unique'    => 'قيمة :attribute مستخدمة بالفعل.',
    'confirmed' => 'حقل تأكيد :attribute غير متطابق.',

    'attributes' => [
        'email'    => 'البريد الإلكتروني',
        'password' => 'كلمة المرور',
        'name'     => 'الاسم',
        'role'     => 'الدور',
        'locale'   => 'اللغة',
    ],
];
