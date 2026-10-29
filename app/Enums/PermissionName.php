<?php

namespace App\Enums;

enum PermissionName: string
{
    case ViewUsers = 'users.view';
    case UpdateUsers = 'users.update';
    case CreateCategories = 'categories.create';
    case UpdateCategories = 'categories.update';
    case DeleteCategories = 'categories.delete';
    case ViewAnyCourses = 'courses.view-any';
    case ViewOwnCourses = 'courses.view-own';
    case CreateCourses = 'courses.create';
    case UpdateAnyCourses = 'courses.update-any';
    case UpdateOwnCourses = 'courses.update-own';
    case DeleteAnyCourses = 'courses.delete-any';
    case DeleteOwnCourses = 'courses.delete-own';
    case PublishAnyCourses = 'courses.publish-any';
    case PublishOwnCourses = 'courses.publish-own';
    case PurchaseCourses = 'courses.purchase';
    case StudyCourses = 'courses.study';
    case ViewAnyLessons = 'lessons.view-any';
    case ViewOwnLessons = 'lessons.view-own';
    case CreateAnyLessons = 'lessons.create-any';
    case CreateOwnLessons = 'lessons.create-own';
    case UpdateAnyLessons = 'lessons.update-any';
    case UpdateOwnLessons = 'lessons.update-own';
    case DeleteAnyLessons = 'lessons.delete-any';
    case DeleteOwnLessons = 'lessons.delete-own';
    case CompleteLessons = 'lessons.complete';
    case ViewOwnCertificates = 'certificates.view-own';
    case DownloadOwnCertificates = 'certificates.download-own';
}
