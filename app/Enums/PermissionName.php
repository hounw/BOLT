<?php

namespace App\Enums;

enum PermissionName: string
{
    case EmployeesView = 'employees.view';
    case EmployeesManage = 'employees.manage';
    case CompensationView = 'hr.compensation.view';
    case CompensationManage = 'hr.compensation.manage';
    case BenefitsView = 'hr.benefits.view';
    case BenefitsManage = 'hr.benefits.manage';
    case PtoView = 'pto.view';
    case PtoManage = 'pto.manage';
    case PtoApprove = 'pto.approve';
    case FilesView = 'files.view';
    case FilesManage = 'files.manage';
    case KnowledgeView = 'knowledge.view';
    case KnowledgeManage = 'knowledge.manage';
    case AssetsView = 'assets.view';
    case AssetsManage = 'assets.manage';
    case AuditView = 'audit.view';
    case WebhooksManage = 'webhooks.manage';
    case ApiClientsManage = 'api.clients.manage';
}
