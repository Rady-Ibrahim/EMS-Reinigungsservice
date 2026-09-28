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

    'current_password_incorrect' => 'كلمة المرور الحالية غير صحيحة.',
    'resolution_required'         => 'وصف الحل مطلوب.',

    'attributes' => [
        'email'            => 'البريد الإلكتروني',
        'password'         => 'كلمة المرور',
        'current_password' => 'كلمة المرور الحالية',
        'resolution'       => 'الحل',
        'name'             => 'الاسم',
        'role'             => 'الدور',
        'locale'           => 'اللغة',
    ],
];
