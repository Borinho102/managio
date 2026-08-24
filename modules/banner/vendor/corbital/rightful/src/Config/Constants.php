<?php

namespace Corbital\Rightful\Config;

enum Constants: string
{
    case CONNECTION_FAILED       = 'Server is unavailable at the moment, please try again.';
    case INVALID_RESPONSE        = 'Server returned an invalid response, please contact support.';
    case VERIFIED_RESPONSE       = 'Verified! Thanks for purchasing.';
    case PREPARING_MAIN_DOWNLOAD = 'Preparing to download main update...';
    case MAIN_UPDATE_SIZE        = 'Main Update size:';
    case PLEASE_DONT_REFRESH     = '(Please do not refresh the page).';
    case DOWNLOADING_MAIN        = 'Downloading main update...';
    case UPDATE_PERIOD_EXPIRED   = 'Your update period has ended or your license is invalid, please contact support.';
    case UPDATE_PATH_ERROR       = 'Folder does not have write permission or the update file path could not be resolved, please contact support.';
    case MAIN_UPDATE_DONE        = 'Main update files downloaded and extracted.';
    case UPDATE_EXTRACTION_ERROR = 'Update zip extraction failed.';
    case PREPARING_SQL_DOWNLOAD  = 'Preparing to download SQL update...';
    case SQL_UPDATE_SIZE         = 'SQL Update size:';
    case DOWNLOADING_SQL         = 'Downloading SQL update...';
    case SQL_UPDATE_DONE         = 'SQL update files downloaded.';
    case SQL_IMPORT_FAILED       = 'Application was successfully updated but automatic SQL importing failed, please import the downloaded SQL file in your database manually.';
    case SQL_IMPORT_SUCCESS      = 'Application was successfully updated and SQL file was automatically imported.';
    case UPDATE_WITHOUT_SQL      = 'Application was successfully updated, there were no SQL updates.';
    case SUPPORT_EXPIRY_MESSAGE  = 'Support has already expired.';
}

// Server-related configurations, for example:
const EXECUTION_TIME = 600;
const MEMORY_LIMIT   = '256M';
