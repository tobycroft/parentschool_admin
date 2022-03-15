<?php

namespace Student;

class Student
{
    public static function get_student_type()
    {
        return ["parent" => "家长", "student" => "学生"];
    }

    public static function get_student_gender()
    {
        return ["0" => "未定义", "1" => "男生", "2" => "女生"];
    }
}