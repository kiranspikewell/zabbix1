<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

namespace Widgets\Honeycomb\Includes;

use Zabbix\Widgets\{
	CWidgetField,
	CWidgetForm,
	Fields\CWidgetFieldCheckBox,
	Fields\CWidgetFieldCheckBoxList,
	Fields\CWidgetFieldColor,
	Fields\CWidgetFieldIntegerBox,
	Fields\CWidgetFieldMultiSelectGroup,
	Fields\CWidgetFieldMultiSelectHost,
	Fields\CWidgetFieldMultiSelectItem,
	Fields\CWidgetFieldRadioButtonList,
	Fields\CWidgetFieldTags,
	Fields\CWidgetFieldTextArea,
	Fields\CWidgetFieldThresholds
};

use Widgets\Honeycomb\Widget;

/**
 * Honeycomb widget form.
 */
class WidgetForm extends CWidgetForm {

	private const SIZE_PERCENT_MIN = 1;
	private const SIZE_PERCENT_MAX = 100;

	private const DEFAULT_PRIMARY_SIZE = 5;
	private const DEFAULT_SECONDARY_SIZE = 10;

	public const SIZE_CUSTOM = 1;
	private const SIZE_AUTO = 0;

	public function addFields(): self {
		return $this
			->addField($this->isTemplateDashboard()
				? null
				: new CWidgetFieldMultiSelectGroup('groupids', _('Host groups'))
			)
			->addField($this->isTemplateDashboard()
				? null
				: new CWidgetFieldMultiSelectHost('hostids', _('Hosts'))
			)
			->addField($this->isTemplateDashboard()
				? null
				: (new CWidgetFieldRadioButtonList('evaltype_host', _('Host tags'), [
					TAG_EVAL_TYPE_AND_OR => _('And/Or'),
					TAG_EVAL_TYPE_OR => _('Or')
				]))->setDefault(TAG_EVAL_TYPE_AND_OR)
			)
			->addField($this->isTemplateDashboard()
				? null
				: new CWidgetFieldTags('host_tags')
			)
			->addField(
				(new CWidgetFieldMultiSelectItem('itemids', _('Item pattern')))
			)
			->addField($this->isTemplateDashboard()
				? null
				: (new CWidgetFieldRadioButtonList('evaltype_item', _('Item tags'), [
					TAG_EVAL_TYPE_AND_OR => _('And/Or'),
					TAG_EVAL_TYPE_OR => _('Or')
				]))->setDefault(TAG_EVAL_TYPE_AND_OR)
			)
			->addField($this->isTemplateDashboard()
				? null
				: new CWidgetFieldTags('item_tags')
			)
			->addField(
				(new CWidgetFieldCheckBox(
					'maintenance',
					$this->isTemplateDashboard() ? _('Show data in maintenance') : _('Show hosts in maintenance')
				))->setDefault(1)
			)
			->addField(
				(new CWidgetFieldCheckBoxList('show', _('Show'), [
					Widget::SHOW_PRIMARY => _('Primary label'),
					Widget::SHOW_SECONDARY => _('Secondary label')
				]))
					->setDefault([Widget::SHOW_PRIMARY])
					->setFlags(CWidgetField::FLAG_LABEL_ASTERISK)
			)
			->addField(
				(new CWidgetFieldTextArea('primary', _('Primary label')))
					->setDefault('{ITEM.NAME}')
					->setFlags(CWidgetField::FLAG_NOT_EMPTY)
			)
			->addField(
				(new CWidgetFieldRadioButtonList('primary_size_type', null, [
					self::SIZE_AUTO => _('Auto'),
					self::SIZE_CUSTOM => _('Custom')
				]))->setDefault(self::SIZE_AUTO)
			)
			->addField(
				(new CWidgetFieldIntegerBox('primary_size', _('Size'), self::SIZE_PERCENT_MIN, self::SIZE_PERCENT_MAX))
					->setDefault(self::DEFAULT_PRIMARY_SIZE)
			)
			->addField(
				new CWidgetFieldCheckBox('primary_bold', _('Bold'))
			)
			->addField(
				new CWidgetFieldColor('primary_color', _('Color'))
			)
			->addField(
				(new CWidgetFieldTextArea('secondary', _('Secondary label')))
					->setDefault('{ITEM.NAME}')
					->setFlags(CWidgetField::FLAG_NOT_EMPTY)
			)
			->addField(
				(new CWidgetFieldRadioButtonList('secondary_size_type', null, [
					self::SIZE_AUTO => _('Auto'),
					self::SIZE_CUSTOM => _('Custom')
				]))->setDefault(self::SIZE_AUTO)
			)
			->addField(
				(new CWidgetFieldIntegerBox('secondary_size', _('Size'), self::SIZE_PERCENT_MIN, self::SIZE_PERCENT_MAX))
					->setDefault(self::DEFAULT_SECONDARY_SIZE)
			)
			->addField(
				(new CWidgetFieldCheckBox('secondary_bold', _('Bold')))->setDefault(1)
			)
			->addField(
				new CWidgetFieldColor('secondary_color', _('Color'))
			)
			->addField(
				new CWidgetFieldColor('bg_color', _('Background color'))
			)
			->addField(
				new CWidgetFieldCheckBox('interpolation', _('Color interpolation'))
			)
			->addField(
				new CWidgetFieldThresholds('thresholds', _('Thresholds'))
			)
			->addField($this->isTemplateDashboard()
				? null
				: new CWidgetFieldCheckBox('dynamic', _('Enable host selection'))
			);
	}
}
