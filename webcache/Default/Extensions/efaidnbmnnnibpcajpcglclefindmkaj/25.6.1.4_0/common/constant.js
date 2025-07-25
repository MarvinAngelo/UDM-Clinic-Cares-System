/*************************************************************************
* ADOBE CONFIDENTIAL
* ___________________
*
*  Copyright 2015 Adobe Systems Incorporated
*  All Rights Reserved.
*
* NOTICE:  All information contained herein is, and remains
* the property of Adobe Systems Incorporated and its suppliers,
* if any.  The intellectual and technical concepts contained
* herein are proprietary to Adobe Systems Incorporated and its
* suppliers and are protected by all applicable intellectual property laws,
* including trade secret and or copyright laws.
* Dissemination of this information or reproduction of this material
* is strictly forbidden unless prior written permission is obtained
* from Adobe Systems Incorporated.
**************************************************************************/
import{getAllowAccessToFileUrl as e,getPinToToolbarUrl as o}from"./locale.js";export const OptionPageActions={OPTIONS_UPDATE_TOGGLE:"OPTIONS_UPDATE_TOGGLE"};export const OptionsPageToggles={ViewerOwnershipTitle:"pdfOwnershipExploreOptionsTitle"};export const LOCAL_FILE_PERMISSION_URL=`chrome://extensions/?id=${chrome.runtime.id}#options-section:~:text=${e()}`;export const PIN_TOOLBAR_URL=`chrome://extensions/?id=${chrome.runtime.id}#options-section:~:text=${o()}`;export const TWO_WEEKS_IN_MS=12096e5;export const ONE_WEEKS_IN_MS=6048e5;export const ONE_DAY_IN_MS=864e5;export const LOCAL_FTE_WINDOW=Object.freeze({height:525,width:605});export const COOLDOWN_FOR_LFT_PROMPT=9e5;export const COOLDOWN_FOR_DOWNLOAD_BANNER=9e5;export const OFFSCREEN_DOCUMENT_PATH="browser/js/offscreen/offscreen.html";export const LOCALES={"da-DK":"Dansk","de-DE":"Deutsch","en-US":"English: US","en-GB":"English: UK","es-ES":"Español","fr-FR":"Français","it-IT":"Italiano","nl-NL":"Nederlands","nb-NO":"Norsk: bokmål","pt-BR":"Português: Brasil","fi-FI":"Suomi","ja-JP":"日本語","sv-SE":"Svenska","ko-KR":"한국어","zh-CN":"简体中文","zh-TW":"繁体中文","cs-CZ":"Čeština","pl-PL":"Polski","ru-RU":"Русский","tr-TR":"Türkçe"};export const validFrictionlessLocales=Object.freeze({cs:"cs-CZ",da:"da-DK",de:"de-DE",en:"en-US",en_GB:"en-GB",en_US:"en-US",es:"es-ES",fi:"fi-FI",fr:"fr-FR",it:"it-IT",ja:"ja-JP",ko:"ko-KR",nb:"nb-NO",nl:"nl-NL",pl:"pl-PL",pt_BR:"pt-BR",ru:"ru-RU",sv:"sv-SE",tr:"tr-TR",zh_TW:"zh-TW",zh_CN:"zh-CN"});export const validFrictionlessOCRLocales=Object.freeze({de:"de-DE",en:"en-US",en_GB:"en-GB",en_US:"en-US",es:"es-ES",ja:"ja-JP",fr:"fr-FR",it:"it-IT",pt_BR:"pt-BR"});export const validFrictionlessChatPdfLocales=Object.freeze({en:"en-US",en_GB:"en-GB",en_US:"en-US"});export const downloadBannerExcludeList=["acrobat.adobe.com","stage.acrobat.adobe.com","dev.acrobat.adobe.com","mail.google.com"];export const ADOBE_URL="https://www.adobe.com/";export const ONBOARDING_DEMO="onboarding-demo";export const demoFileURL="https://acrobat.adobe.com/dc-files2-dropin/demo-files/en-US/onboarding-demo/onboarding-demo.pdf";