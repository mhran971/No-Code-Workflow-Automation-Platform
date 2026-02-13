<?php

namespace Modules\Auth\Enums;

enum BusinessType: string
{
    case SaaS = 'saas';
    case SoftwareHouse = 'software_house';
    case DigitalAgency = 'digital_agency';
    case TechStartup = 'tech_startup';
    case ITConsultingFirm = 'it_consulting_firm';
    case CloudServiceProvider = 'cloud_service_provider';
    case CybersecurityCompany = 'cybersecurity_company';
    case GameDevelopmentStudio = 'game_development_studio';
    case IoTSolutionsProvider = 'iot_solutions_provider';
    case DevOpsServicesCompany = 'devops_services_company';
    case ARVRDevelopmentCompany = 'ar_vr_development_company';
    case RoboticsAndAutomationFirm = 'robotics_and_automation_firm';

    /**
     * Human-readable labels for dropdown display.
     */
    public static function labels(): array
    {
        return [
            self::SaaS->value => 'SaaS (Software as a Service)',
            self::SoftwareHouse->value => 'Software House',
            self::DigitalAgency->value => 'Digital Agency',
            self::TechStartup->value => 'Tech Startup',
            self::ITConsultingFirm->value => 'IT Consulting Firm',
            self::CloudServiceProvider->value => 'Cloud Service Provider',
            self::CybersecurityCompany->value => 'Cybersecurity Company',
            self::GameDevelopmentStudio->value => 'Game Development Studio',
            self::IoTSolutionsProvider->value => 'IoT Solutions Provider',
            self::DevOpsServicesCompany->value => 'DevOps Services Company',
            self::ARVRDevelopmentCompany->value => 'AR/VR Development Company',
            self::RoboticsAndAutomationFirm->value => 'Robotics and Automation Firm',
        ];
    }
}
