<?php
/**
 * Russian (ru_RU) translation for Delete Guard.
 *
 * @package Delete_Guard
 */

return [
	'project-id-version' => 'Delete Guard 1.0.0',
	'language' => 'ru_RU',
	'plural-forms' => 'nplurals=3; plural=(n%10==1 && n%100!=11 ? 0 : n%10>=2 && n%10<=4 && (n%100<10 || n%100>=20) ? 1 : 2);',
	'x-domain' => 'delete-guard',
	'messages' => [
		'Delete Guard' => 'Delete Guard',
		'Per post type deletion policies: logging, trash blocking, and permanent delete protection.' => 'Политики удаления постов по типу: логирование, запрет корзины и полного удаления.',
		'Settings saved.' => 'Настройки сохранены.',
		'%d log entry deleted.' . "\0" . '%d log entries deleted.' => [
			'Удалена %d запись лога.',
			'Удалено %d записи лога.',
			'Удалено записей лога: %d.',
		],
		'Settings' => 'Настройки',
		'Log' => 'Лог',
		'New post types are off by default. Administrators and super admins can delete even when a mode blocks it; those actions are still logged.' => 'По умолчанию для новых типов режим выключен. Администраторы и суперадмины могут удалять при любом запрете; действие всё равно пишется в лог.',
		'Navigation Menus (wp_navigation) are block-theme menus. Classic Appearance → Menus use Navigation Menu items (nav_menu_item); if that row is Off, it follows the Navigation Menus mode.' => '«Меню навигации» (wp_navigation) — меню блочной темы. Классические меню (Внешний вид → Меню) — это пункты nav_menu_item; если для них режим Выключено, действует режим «Меню навигации».',
		'Current <code>EMPTY_TRASH_DAYS</code>: <strong>%s</strong>.' => 'Текущий <code>EMPTY_TRASH_DAYS</code>: <strong>%s</strong>.',
		'Post type' => 'Тип поста',
		'Mode' => 'Режим',
		'Allow EMPTY_TRASH_DAYS cleanup' => 'Разрешить удаление по EMPTY_TRASH_DAYS',
		'Via cron' => 'Через cron',
		'Save settings' => 'Сохранить настройки',
		'Status' => 'Статус',
		'All' => 'Все',
		'Action' => 'Действие',
		'Type' => 'Тип',
		'Filter' => 'Фильтр',
		'Delete log entries older than 30 days?' => 'Удалить логи старше 30 дней?',
		'Delete logs older than 30 days' => 'Удалить логи старше 30 дней',
		'Date' => 'Дата',
		'User' => 'Пользователь',
		'Source' => 'Источник',
		'Post' => 'Пост',
		'No entries.' => 'Записей нет.',
		'Off' => 'Выключено',
		'Log only' => 'Только логирование',
		'Block trash' => 'Запрет на перемещение в корзину',
		'Block permanent delete' => 'Запрет на полное удаление',
		'Allowed' => 'Одобрено',
		'Denied' => 'Отказ',
		'Move to trash' => 'В корзину',
		'Permanent delete' => 'Полное удаление',
		'Restore' => 'Восстановление',
		'System' => 'Система',
		'(no title)' => '(без названия)',
	],
];
