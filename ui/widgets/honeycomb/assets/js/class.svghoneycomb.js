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


class CSVGHoneycomb {

	static ZBX_STYLE_CLASS =					'svg-honeycomb';
	static ZBX_STYLE_HONEYCOMB_CONTAINER =		'svg-honeycomb-container';
	static ZBX_STYLE_ROW =						'svg-honeycomb-row';
	static ZBX_STYLE_CELL =						'svg-honeycomb-cell';
	static ZBX_STYLE_CELL_NO_DATA =				'svg-honeycomb-cell-no-data';
	static ZBX_STYLE_CELL_POPPED =				'svg-honeycomb-cell-popped';
	static ZBX_STYLE_CELL_OTHER =				'svg-honeycomb-cell-other';
	static ZBX_STYLE_CELL_OTHER_ELLIPSIS =		'svg-honeycomb-cell-other-ellipsis';
	static ZBX_STYLE_LABEL =					'svg-honeycomb-label';
	static ZBX_STYLE_LABEL_PRIMARY =			'svg-honeycomb-label-primary';
	static ZBX_STYLE_LABEL_SECONDARY =			'svg-honeycomb-label-secondary';

	static ID_COUNTER = 0;

	static CELL_WIDTH_MIN = 70;
	static LABEL_HEIGHT_MIN = 12;

	static LABEL_PRIMARY_SIZE_DEFAULT = 20;
	static LABEL_SECONDARY_SIZE_DEFAULT = 30;

	/**
	 * Widget configuration.
	 *
	 * @type {Object}
	 */
	#config;

	/**
	 * Inner padding of the root SVG element.
	 *
	 * @type {Object}
	 */
	#padding;

	/**
	 * Root SVG element.
	 *
	 * @type {SVGSVGElement}
	 * @member {Selection}
	 */
	#svg;

	/**
	 * SVG group element implementing scaling and fitting of its contents inside the root SVG element.
	 *
	 * @type {SVGGElement}
	 * @member {Selection}
	 */
	#g_scalable;

	/**
	 * Usable width of widget without padding.
	 *
	 * @type {number}
	 */
	#width;

	/**
	 * Usable height of widget without padding.
	 *
	 * @type {number}
	 */
	#height;

	/**
	 * Outer radius of cell.
	 * It is large number because SVG works more precise that way (later it will be scaled according to widget size).
	 *
	 * @type {number}
	 */
	#radius_outer = 1000;

	/**
	 * Inner radius of cell.
	 *
	 * @type {number}
	 */
	#radius_inner = Math.sqrt(3) / 2 * this.#radius_outer;

	/**
	 * Gap between cells.
	 *
	 * @type {number}
	 */
	#cells_gap = this.#radius_outer / 10;

	/**
	 * Data about cells.
	 *
	 * @type {Array}
	 */
	#cells_data = [];

	/**
	 * Data about cells structured in rows and columns.
	 *
	 * @type {Array}
	 */
	#cells_data_structured = [];

	/**
	 * Number of columns of honeycomb cells.
	 *
	 * @type {number}
	 */
	#column_count = 1;

	/**
	 * Number of rows of honeycomb cells.
	 *
	 * @type {number}
	 */
	#row_count = 1;

	/**
	 * Scale of honeycomb.
	 *
	 * @type {number}
	 */
	#scale = 1;

	/**
	 * Ratio that is the closest possible of honeycomb and widget container.
	 *
	 * @type {Object}
	 */
	#closest_ratio = {};

	/**
	 * Created SVG child elements of honeycomb.
	 *
	 * @type {Object}
	 */
	#elements = {};

	/**
	 * Line count of primary label.
	 *
	 * @type {number}
	 */
	#label_primary_line_count = 0;

	/**
	 * Line count of secondary label.
	 *
	 * @type {number}
	 */
	#label_secondary_line_count = 0;

	/**
	 * Font size of primary label.
	 *
	 * @type {number}
	 */
	#label_primary_font_size = 0;

	/**
	 * Font size of secondary label.
	 *
	 * @type {number}
	 */
	#label_secondary_font_size = 0;

	/**
	 * @param {Object} padding             Inner padding of the root SVG element.
	 *        {number} padding.horizontal
	 *        {number} padding.vertical
	 *
	 * @param {Object} config              Widget configuration.
	 */
	constructor(padding, config) {
		this.#config = config;
		this.#padding = padding;

		this.#svg = d3.create('svg:svg')
			.attr('class', CSVGHoneycomb.ZBX_STYLE_CLASS)
			.on('click', (e) => this.#onClickSvg(e));

		this.#createContainers();

		CSVGHoneycomb.ID_COUNTER++;
	}

	/**
	 * Set size of the root SVG element and re-position the elements.
	 *
	 * @param {number} width
	 * @param {number} height
	 */
	setSize({width, height}) {
		this.#width = width - this.#padding.horizontal * 2;
		this.#height = height - this.#padding.vertical * 2;

		this.#svg
			.attr('width', width)
			.attr('height', height);

		if (this.#closest_ratio?.ratio) {
			const closest_ratio_new = this.#getClosestRatio(this.#cells_data.length);

			if (this.#closest_ratio.ratio !== closest_ratio_new.ratio) {
				this.#elements.honeycomb_container.html('');
				this.#cells_data_structured = [];

				this.#column_count = Math.ceil(this.#cells_data.length / closest_ratio_new.rows);
				this.#row_count = closest_ratio_new.rows;

				for (let i = 0; i < this.#cells_data.length; i += this.#column_count) {
					const row = this.#cells_data.slice(i, i + this.#column_count);
					this.#cells_data_structured.push(row);
				}

				this.#drawCells(this.#cells_data_structured);

				this.#closest_ratio = closest_ratio_new;
			}
		}

		this.#adjustSize();
	}

	/**
	 * Set value (cells) of honeycomb.
	 *
	 * @param {Array} cells  Array of cells to show in honeycomb.
	 */
	setValue({cells}) {
		this.#cells_data = cells;

		this.#elements.honeycomb_container.html('');
		this.#cells_data_structured = [];

		if (this.#cells_data.length > 0) {
			this.#closest_ratio = this.#getClosestRatio(this.#cells_data.length);

			this.#column_count = Math.ceil(this.#cells_data.length / this.#closest_ratio.rows);
			this.#row_count = this.#closest_ratio.rows;

			for (let i = 0; i < this.#cells_data.length; i += this.#column_count) {
				const row = this.#cells_data.slice(i, i + this.#column_count);
				this.#cells_data_structured.push(row);
			}

			this.#drawCells(this.#cells_data_structured);
		}
		else {
			this.#elements.no_data_cell
				.style('display', 'block');
		}

		this.#adjustSize();
	}

	/**
	 * Get the root SVG element.
	 *
	 * @returns {SVGSVGElement}
	 */
	getSVGElement() {
		return this.#svg.node();
	}

	/**
	 * Remove created SVG element from the container.
	 */
	destroy() {
		this.#svg.node().remove();
	}

	/**
	 * Calculate honeycomb ratio that is the closest possible to widget container ratio.
	 *
	 * @returns {Object}
	 */
	#getClosestRatio(cells_count) {
		const ratios_possible = [];

		let columns = 1;

		for (columns; columns < cells_count + 1; columns++) {
			let rows = 1;

			if (cells_count % columns === 0) {
				rows = cells_count / columns;
			}
			else {
				rows = Math.ceil(cells_count / columns);
			}

			ratios_possible.push({
				columns: columns,
				rows: rows,
				ratio: columns / rows
			});
		}

		const ratio_svg = this.#width / this.#height;

		return ratios_possible.reduce((prev, curr) =>
			Math.abs(curr?.ratio - ratio_svg) < Math.abs(prev?.ratio - ratio_svg) ? curr : prev
		);
	}

	/**
	 * Adjust size of honeycomb.
	 */
	#adjustSize() {
		const getHoneycombSize = () => {
			let width = this.#radius_inner * 2 * this.#column_count
				+ this.#cells_gap * this.#column_count
				- this.#cells_gap;

			if (this.#row_count > 1 && this.#cells_data_structured[1]?.length === this.#column_count) {
				// Take into account width of half cell of even rows.
				width += this.#radius_inner + this.#cells_gap / 2;
			}

			let height = this.#radius_outer * 2 * this.#row_count
				- this.#radius_outer / 2 * (this.#row_count - 1)
				+ this.#cells_gap * this.#row_count
				- this.#cells_gap;

			return {width, height};
		};

		let honeycomb_size = getHoneycombSize();

		this.#scale = Math.min(this.#width / honeycomb_size.width, this.#height / honeycomb_size.height);

		if (this.#cells_data.length > 0) {
			const cell_min_radius_inner = CSVGHoneycomb.CELL_WIDTH_MIN / 2;
			const cell_min_radius_outer = cell_min_radius_inner / Math.sqrt(3) * 2;
			const cell_radius_inner = this.#radius_inner * this.#scale;

			if (cell_radius_inner < cell_min_radius_inner) {
				let cells_gap_new = cell_min_radius_outer / 10;

				this.#row_count = Math.floor(this.#height / (cell_min_radius_outer + cell_min_radius_outer / 2 + cells_gap_new));
				if (this.#row_count < 1) {
					this.#row_count = 1;
				}

				this.#column_count = Math.floor(this.#width / (cell_min_radius_inner * 2 - cells_gap_new));
				if (this.#column_count < 1) {
					this.#column_count = 1;
				}

				let cell_count = this.#row_count * this.#column_count;

				if (cell_count > this.#cells_data.length) {
					cell_count = this.#cells_data.length;
					this.#row_count = this.#closest_ratio.rows;
					this.#column_count = this.#closest_ratio.columns;
				}

				this.#closest_ratio = this.#getClosestRatio(cell_count);

				this.#cells_data_structured = [];

				for (let i = 0; i < cell_count; i += this.#column_count) {
					const row = this.#cells_data.slice(i, i + this.#column_count);
					this.#cells_data_structured.push(row);
				}

				if (this.#cells_data.length > cell_count) {
					this.#cells_data_structured[this.#row_count - 1][this.#column_count - 1] = {
						itemid: 'other',
						primary_label: '',
						secondary_label: ''
					};
				}

				this.#elements.honeycomb_container.html('');

				this.#drawCells(this.#cells_data_structured);
				this.#drawOtherCell();
			}
			else {
				this.#column_count = Math.ceil(this.#cells_data.length / this.#closest_ratio.rows);
				this.#row_count = this.#closest_ratio.rows;

				this.#closest_ratio = this.#getClosestRatio(this.#cells_data.length);

				this.#elements.honeycomb_container.html('');
				this.#cells_data_structured = [];

				for (let i = 0; i < this.#cells_data.length; i += this.#column_count) {
					const row = this.#cells_data.slice(i, i + this.#column_count);
					this.#cells_data_structured.push(row);
				}

				this.#drawCells(this.#cells_data_structured);
			}
		}

		honeycomb_size = getHoneycombSize();

		this.#scale = Math.min(this.#width / honeycomb_size.width, this.#height / honeycomb_size.height);

		// Offset for cell pop out.
		this.#scale -= this.#scale / 10;

		const position_start_x = this.#radius_inner * this.#scale;
		const position_centered_x = this.#width / 2 - honeycomb_size.width * this.#scale / 2;
		const x = position_start_x + position_centered_x;

		const position_start_y = this.#radius_outer * this.#scale;
		const position_centered_y = this.#height / 2 - honeycomb_size.height * this.#scale / 2;
		const y = position_start_y + position_centered_y;

		this.#g_scalable.attr('transform', `translate(${x} ${y}) scale(${this.#scale})`);

		const labels_primary = this.#elements.honeycomb_container
			.selectAll(`.${CSVGHoneycomb.ZBX_STYLE_LABEL_PRIMARY}`);
		const labels_secondary = this.#elements.honeycomb_container
			.selectAll(`.${CSVGHoneycomb.ZBX_STYLE_LABEL_SECONDARY}`);

		this.#positionLabels(labels_primary, labels_secondary);

		if ((!labels_primary.empty() && isVisible(labels_primary.node()))
				&& (labels_secondary.empty() || !isVisible(labels_secondary.node()))) {
			let label_primary_position_y = this.#label_primary_font_size / 2;
			label_primary_position_y -= this.#label_primary_font_size / 2 * (this.#label_primary_line_count - 1);

			labels_primary.attr('transform', `translate(0 ${label_primary_position_y})`);
		}

		if ((labels_primary.empty() || !isVisible(labels_primary.node()))
				&& (!labels_secondary.empty() && isVisible(labels_secondary.node()))) {
			let label_secondary_position_y = this.#label_secondary_font_size / 2;
			label_secondary_position_y -= this.#label_secondary_font_size / 2 * (this.#label_secondary_line_count - 1);

			labels_secondary.attr('transform', `translate(0 ${label_secondary_position_y})`);
		}

		const available_cell_height = this.#radius_outer * this.#scale - this.#radius_outer * this.#scale / 10;

		if ((!labels_primary.empty() && isVisible(labels_primary.node()))
				&& (!labels_secondary.empty() && isVisible(labels_secondary.node()))) {
			const label_primary_height = this.#label_primary_font_size * this.#scale;
			const label_secondary_height = this.#label_secondary_font_size * this.#scale;

			if (label_primary_height + label_secondary_height > available_cell_height) {
				labels_primary.style('display', 'none');
				labels_secondary.style('display', 'none');
			}
			else {
				labels_primary.style('display', 'block');
				labels_secondary.style('display', 'block');
			}
		}
	}

	/**
	 * Create containers for elements (cells, no data cell, popped cell).
	 */
	#createContainers() {
		// SVG group element implementing padding inside the root SVG element.
		const main = this.#svg
			.append('svg:g')
			.attr('transform', `translate(${this.#padding.horizontal} ${this.#padding.vertical})`);

		this.#g_scalable = main.append('svg:g');

		this.#drawNoData();
		this.#drawHoneycombContainer();
		this.#drawPoppedCell();
		this.#drawPoppedCellShadow();
	}

	/**
	 * Reserve place for no data cell.
	 */
	#drawNoData() {
		this.#elements.no_data_cell = this.#g_scalable
			.append('svg:g')
			.attr('class', CSVGHoneycomb.ZBX_STYLE_CELL_NO_DATA)
			.style('display', 'none');

		this.#elements.no_data_cell
			.append('svg:path')
			.attr('d', this.#generatePath());

		const font_size = 200;
		const position_y = font_size / 2;

		this.#elements.no_data_cell
			.append('svg:text')
			.text(t('No data'))
			.attr('transform', `translate(0 ${position_y})`)
			.style('font-size', `${font_size}px`);
	};

	/**
	 * Reserve place for honeycomb cells.
	 */
	#drawHoneycombContainer() {
		this.#elements.honeycomb_container = this.#g_scalable
			.append('svg:g')
			.attr('class', CSVGHoneycomb.ZBX_STYLE_HONEYCOMB_CONTAINER);
	}

	/**
	 * Reserve place for popped out cell at the bottom of SVG tree to display it above honeycomb.
	 */
	#drawPoppedCell() {
		this.#elements.popped_cell = this.#g_scalable
			.append('svg:g')
			.attr('class', `${CSVGHoneycomb.ZBX_STYLE_CELL} ${CSVGHoneycomb.ZBX_STYLE_CELL_POPPED}`)
			.attr('data-hintbox', 1)
			.attr('data-hintbox-static', 1)
			.attr('data-hintbox-track-mouse', 1)
			.attr('data-hintbox-delay', 0)
			.on('mouseleave', () => this.#popInCell());

		this.#elements.popped_cell_simple = null;
		this.#elements.popped_cell_static = null;
	}

	/**
	 * Add filter element for shadow of popped cell.
	 */
	#drawPoppedCellShadow() {
		const defs = this.#svg.append('svg:defs');

		const filter = defs
			.append('svg:filter')
			.attr('id', 'popped-cell-shadow')
			.attr('width', '200%')
			.attr('height', '200%')
			.attr('x', '-50%')
			.attr('y', '-50%');

		filter
			.append('svg:feDropShadow')
			.attr('dx', 0)
			.attr('dy', 0)
			.attr('stdDeviation', this.#radius_outer * 0.2)
			.attr('flood-opacity', 0.7);
	}

	/**
	 * Draw honeycomb cells.
	 *
	 * @param {Array} data  Cell data structured in rows and columns.
	 */
	#drawCells(data) {
		this.#elements.honeycomb_rows = this.#elements.honeycomb_container
			.selectAll(`g.${CSVGHoneycomb.ZBX_STYLE_ROW}`)
			.data(data)
			.join('svg:g')
			.attr('class', CSVGHoneycomb.ZBX_STYLE_ROW)
			.attr('transform', (d, index) => {
				let x = 0;
				let y = (this.#radius_outer + this.#radius_outer / 2) * index + (this.#cells_gap * index);

				if (index % 2 !== 0) {
					x = this.#radius_inner + this.#cells_gap / 2;
				}

				return `translate(${x} ${y})`;
			});

		this.#elements.honeycomb_cells = this.#elements.honeycomb_rows
			.selectAll(`g.${CSVGHoneycomb.ZBX_STYLE_CELL}`)
			.data(d => d)
			.join('svg:g')
			.attr('id', d => `${CSVGHoneycomb.ZBX_STYLE_CELL}-${d.itemid}-${CSVGHoneycomb.ID_COUNTER}`)
			.attr('class', (d) => {
				let result = CSVGHoneycomb.ZBX_STYLE_CELL;

				if (d.itemid === 'other') {
					result += ` ${CSVGHoneycomb.ZBX_STYLE_CELL_OTHER}`;
				}

				return result;
			})
			.attr('transform', (d, index) =>
				`translate(${(this.#radius_inner * 2 * index) + (this.#cells_gap * index)} 0)`
			)
			.on('mouseenter', (e) => this.#popOutCell(e.target));

		this.#elements.honeycomb_cells
			.append('svg:path')
			.attr('d', this.#generatePath())
			.style('fill', d => {
				if (!d.is_numeric) {
					// Do not apply thresholds to non-numeric items
					return;
				}

				if (this.#config.thresholds.length === 0) {
					return `#${this.#config.bg_color}`;
				}

				const value = parseFloat(d.value);
				const threshold_type = d.is_binary_units ? 'threshold_binary' : 'threshold';
				const apply_interpolation = this.#config.apply_interpolation && this.#config.thresholds.length > 1;

				for (let i = 0; i < this.#config.thresholds.length; i++) {
					const first = this.#config.thresholds[0];
					const last = this.#config.thresholds[this.#config.thresholds.length - 1];
					const curr = this.#config.thresholds[i];
					const next = this.#config.thresholds[i + 1] || null;

					if (value < first[threshold_type]) {
						if (apply_interpolation) {
							return `#${first.color}`;
						}

						return `#${this.#config.bg_color}`;
					}
					else if (value >= curr[threshold_type] && value < next?.[threshold_type]) {
						if (apply_interpolation) {
							// Position [0..1] of cell value between two adjacent thresholds
							const position = (value - curr[threshold_type])
								/ (next[threshold_type] - curr[threshold_type]);

							const colorRgb = d3.interpolateRgb(`#${curr.color}`, `#${next.color}`)(position);

							return d3.color(colorRgb).formatHex();
						}

						return `#${curr.color}`;
					}
					else if (value >= last[threshold_type]) {
						return `#${last.color}`;
					}
				}
			});

		this.#drawLabels();
	};

	/**
	 * Draw primary and secondary labels of cell.
	 */
	#drawLabels() {
		let labels_primary = null;
		let labels_secondary = null;

		if (this.#config.primary_label.show) {
			labels_primary = this.#elements.honeycomb_cells
				.append('svg:text')
				.attr('class', `${CSVGHoneycomb.ZBX_STYLE_LABEL} ${CSVGHoneycomb.ZBX_STYLE_LABEL_PRIMARY}`)
				.style('fill', `#${this.#config.primary_label.color}`)
				.style('font-weight', this.#config.primary_label.is_bold ? 'bold' : '')
				.style('display', 'block');

			this.#label_primary_line_count = labels_primary.datum().primary_label.split('\r\n').length;

			for (let i = 0; i < this.#label_primary_line_count; i++) {
				labels_primary
					.append('svg:tspan')
					.text(d => d.primary_label.split('\r\n')[i])
					.attr('x', '0');
			}
		}

		if (this.#config.secondary_label.show) {
			labels_secondary = this.#elements.honeycomb_cells
				.append('svg:text')
				.attr('class', `${CSVGHoneycomb.ZBX_STYLE_LABEL} ${CSVGHoneycomb.ZBX_STYLE_LABEL_SECONDARY}`)
				.style('fill', `#${this.#config.secondary_label.color}`)
				.style('font-weight', this.#config.secondary_label.is_bold ? 'bold' : '')
				.style('display', 'block');

			this.#label_secondary_line_count = labels_secondary.datum().secondary_label.split('\r\n').length;

			for (let i = 0; i < this.#label_secondary_line_count; i++) {
				labels_secondary
					.append('svg:tspan')
					.text(d => d.secondary_label.split('\r\n')[i])
					.attr('x', '0');
			}
		}

		if (this.#config.primary_label.show || this.#config.secondary_label.show) {
			this.#positionLabels(labels_primary, labels_secondary);
		}
	};

	/**
	 * Position primary and secondary labels in cell.
	 *
	 * @param {Selection} primary       Selection of primary labels.
	 * @param {Selection} secondary     Selection of secondary labels.
	 * @param {boolean}   check_height  Hide labels or not when smaller than limit.
	 */
	#positionLabels(primary, secondary, check_height = true) {
		const getAutoLabelSize = (labels, data_attribute, default_size) => {
			const lines_widths = [];

			labels
				.each((d, index_label, nodes) => {
					const lines = d[data_attribute].split('\r\n');

					d3.select(nodes[index_label])
						.selectAll('tspan')
						.each((d, index_line) => {
							lines_widths.push(this.#getMeasuredTextWidth(
								lines[index_line],
								default_size * 10,
								this.#svg.style('font-family')) * this.#scale)
						});
				});

			const longest_label_width = d3.max(lines_widths);

			const available_cell_width = this.#radius_inner * 2 * this.#scale - this.#radius_inner * this.#scale / 10;

			let coefficient = available_cell_width / longest_label_width;

			if (coefficient > 1) {
				coefficient = 1;
			}

			let label_height = default_size * 10 * this.#scale * coefficient;

			if (label_height < CSVGHoneycomb.LABEL_HEIGHT_MIN) {
				coefficient = CSVGHoneycomb.LABEL_HEIGHT_MIN / (default_size * 10 * this.#scale);
			}

			return default_size * 10 * coefficient;
		};

		let label_primary_position_y = 0;
		let label_secondary_position_y = 0;

		if (this.#config.primary_label.show) {
			if (this.#config.primary_label.is_custom_size) {
				this.#label_primary_font_size = this.#config.primary_label.size * 10;
			}
			else {
				this.#label_primary_font_size = getAutoLabelSize(
					primary,
					'primary_label',
					CSVGHoneycomb.LABEL_PRIMARY_SIZE_DEFAULT
				);
			}

			if (this.#config.secondary_label.show) {
				label_primary_position_y = -this.#radius_outer / 2 + this.#label_primary_font_size;
			}
			else {
				label_primary_position_y = this.#label_primary_font_size / 2
					- this.#label_primary_font_size / 2 * (this.#label_primary_line_count - 1);
			}

			primary
				.attr('transform', `translate(0 ${label_primary_position_y})`)
				.style('font-size', `${this.#label_primary_font_size}px`);

			primary
				.selectAll('tspan')
				.attr('y', (d, index) => this.#label_primary_font_size * index);
		}

		if (this.#config.secondary_label.show) {
			if (this.#config.secondary_label.is_custom_size) {
				this.#label_secondary_font_size = this.#config.secondary_label.size * 10;

				if (this.#config.primary_label.show
						&& this.#config.primary_label.size + this.#config.secondary_label.size > 100) {
					this.#label_secondary_font_size = (100 - this.#config.primary_label.size) * 10;
				}
			}
			else {
				this.#label_secondary_font_size = getAutoLabelSize(
					secondary,
					'secondary_label',
					CSVGHoneycomb.LABEL_SECONDARY_SIZE_DEFAULT
				);
			}

			if (this.#config.primary_label.show) {
				label_secondary_position_y = this.#radius_outer / 2
					- this.#label_secondary_font_size / 2 * (this.#label_secondary_line_count - 1);

			}
			else {
				label_secondary_position_y = this.#label_secondary_font_size / 2;
			}

			secondary
				.attr('transform', `translate(0 ${label_secondary_position_y})`)
				.style('font-size', `${this.#label_secondary_font_size}px`);

			secondary
				.selectAll('tspan')
				.attr('y', (d, index) => this.#label_secondary_font_size * index);
		}

		if (this.#config.primary_label.show && this.#config.secondary_label.show) {
			const total_height = this.#label_primary_font_size * this.#label_primary_line_count
				+ this.#label_secondary_font_size * this.#label_secondary_line_count;

			// If both labels in total don't occupy all available space - disperse them vertically
			if (total_height < this.#radius_outer) {
				const offset = (this.#radius_outer - total_height) / 3;

				primary.attr('transform',
					`translate(0 ${label_primary_position_y + offset / this.#label_primary_line_count / 2})`
				);
				secondary.attr('transform',
					`translate(0 ${label_secondary_position_y - offset / this.#label_secondary_line_count / 2})`
				);
			}
		}

		if (check_height) {
			const check_labels_height = (labels, font_size) => {
				const node = labels?.node();

				if (node) {
					const correction = 1; // For floating point errors.
					let height_actual = font_size * this.#scale + correction;

					if (height_actual < CSVGHoneycomb.LABEL_HEIGHT_MIN) {
						labels.style('display', 'none');
					}
					else {
						labels.style('display', 'block');
					}
				}
			};

			check_labels_height(primary, this.#label_primary_font_size);
			check_labels_height(secondary, this.#label_secondary_font_size);
		}

		const available_cell_width = this.#radius_inner * 2 - this.#radius_inner / 10;

		const adjust_labels_width = (labels, data_attribute) => {
			labels
				?.each((d, index_label, nodes) => {
					const lines = d[data_attribute].split('\r\n');

					d3.select(nodes[index_label])
						.selectAll('tspan')
						.each((d, index_line, nodes) => {
							nodes[index_line].textContent = lines[index_line];
							while (nodes[index_line].getComputedTextLength() > available_cell_width) {
								nodes[index_line].textContent = `${nodes[index_line].textContent.slice(0, -4)}...`;
							}
						});
				});
		};

		adjust_labels_width(primary, 'primary_label');
		adjust_labels_width(secondary, 'secondary_label');
	};

	/**
	 * Draw "other" cell that indicates that all cells do not fit in available space in widget.
	 */
	#drawOtherCell() {
		const other = this.#svg
			.select(`.${CSVGHoneycomb.ZBX_STYLE_CELL_OTHER}`);

		other
			.selectAll(`.${CSVGHoneycomb.ZBX_STYLE_LABEL}`)
			.remove();

		const radius = this.#radius_inner / 10;
		const offset = radius * 4;

		const ellipsis = other
			.append('svg:g')
			.attr('class', CSVGHoneycomb.ZBX_STYLE_CELL_OTHER_ELLIPSIS)
			.attr('transform', `translate(${-offset} 0)`);

		for (let i = 0; i < 3; i++) {
			ellipsis
				.append('svg:circle')
				.attr('r', radius)
				.attr('cx', offset * i);
		}
	}

	/**
	 * Pop out (enlarge) cell from its initial position.
	 *
	 * @param {Element} target  Cell element to pop out.
	 */
	#popOutCell(target) {
		if (target.classList.contains(CSVGHoneycomb.ZBX_STYLE_CELL_OTHER)) {
			return;
		}

		this.#elements.popped_cell_simple = d3.select(target);

		const row = target.closest(`.${CSVGHoneycomb.ZBX_STYLE_ROW}`);
		const row_matrix = row.transform.baseVal[0].matrix;
		const row_translate_x = row_matrix.e;
		const row_translate_y = row_matrix.f;

		const cell_matrix = target.transform.baseVal[0].matrix;
		const cell_translate_x = cell_matrix.e;
		const cell_translate_y = cell_matrix.f;

		const popped_translate_x = row_translate_x + cell_translate_x;
		const popped_translate_y = row_translate_y + cell_translate_y;

		const scale_popped = 1.3;

		this.#elements.popped_cell
			.datum(this.#elements.popped_cell_simple.datum())
			.html(this.#elements.popped_cell_simple.html())
			.attr('data-hintbox-contents', d => d.hint_text)
			.attr('transform',
				`translate(${popped_translate_x} ${popped_translate_y}) scale(${scale_popped})`
			)
			.style('display', 'block');

		this.#elements.popped_cell
			.selectAll(`.${CSVGHoneycomb.ZBX_STYLE_LABEL}`)
			.style('display', 'block');

		const labels_primary = this.#elements.popped_cell.select(`.${CSVGHoneycomb.ZBX_STYLE_LABEL_PRIMARY}`);
		const labels_secondary = this.#elements.popped_cell.select(`.${CSVGHoneycomb.ZBX_STYLE_LABEL_SECONDARY}`);

		// Need to position in case if any label was hidden due to small height,
		// because in popped cell both labels must be visible.
		this.#positionLabels(labels_primary, labels_secondary, false);

		const cell_color = this.#elements.popped_cell
			.select('path')
			.style('fill');

		const stroke_color = d3.color(cell_color).darker(1);

		this.#elements.popped_cell
			.select('path')
			.style('filter', 'url(#popped-cell-shadow)')
			.style('stroke', stroke_color)
			.style('stroke-width', this.#radius_inner / 100);
	};

	/**
	 * Pop in (smallen) popped cell back to its initial position.
	 */
	#popInCell() {
		this.#elements.popped_cell
			.style('display', 'none')
			.attr('transform', '')
			.attr('data-hintbox-contents', '')
			.html('')
			.datum(null);

		this.#elements.popped_cell_simple = null;
		this.#elements.popped_cell_static = null;
	};

	/**
	 * Process mouse click event on SVG to determine whether to pop out or pop in cell.
	 *
	 * @param {Event} e  Mouse click event.
	 */
	#onClickSvg(e) {
		if (this.#cells_data.length === 0) {
			return;
		}

		const processClickOnCell = () => {
			this.#elements.popped_cell_static = this.#elements.popped_cell_simple;

			this.#elements.honeycomb_cells.on('mouseenter', null);
			this.#elements.popped_cell.on('mouseleave', null);

			const hostid = this.#elements.popped_cell.datum().hostid;
			const itemid = this.#elements.popped_cell.datum().itemid;

			this.#svg.node().dispatchEvent(new CustomEvent('cell.pop.out', {detail: {hostid, itemid}}));
		};

		const clicked_cell = e.target.closest(`.${CSVGHoneycomb.ZBX_STYLE_CELL}`);
		const clicked_on_popped_cell = !!e.target.closest(`.${CSVGHoneycomb.ZBX_STYLE_CELL_POPPED}`);
		const clicked_on_another_cell = clicked_cell
			&& this.#elements.popped_cell?.node() !== clicked_cell
			&& !clicked_cell.classList.contains(CSVGHoneycomb.ZBX_STYLE_CELL_OTHER);

		if (clicked_on_popped_cell && this.#elements.popped_cell_static === null) {
			processClickOnCell();
		}
		else if (clicked_on_another_cell) {
			this.#popInCell();
			this.#popOutCell(clicked_cell);

			processClickOnCell();
		}
		else if (this.#elements.popped_cell_static !== null) {
			this.#popInCell();

			this.#elements.honeycomb_cells.on('mouseenter', (e) => this.#popOutCell(e.target));
			this.#elements.popped_cell.on('mouseleave', () => this.#popInCell());

			this.#svg.node().dispatchEvent(new CustomEvent('cell.pop.in'));
		}
	};

	/**
	 * Generate d attribute of path element to display hexagonal cell.
	 *
	 * @returns {string}  The d attribute of path element.
	 */
	#generatePath() {
		const getCorners = () => {
			const getPositionOnLine = (start, end, distance) => {
				const x = start[0] + (end[0] - start[0]) * distance;
				const y = start[1] + (end[1] - start[1]) * distance;

				return [x, y];
			};

			const corner_count = 6;
			const corner_radius = 0.075;
			const handle_distance111 = corner_radius - (corner_radius * 0.5);
			const offset = Math.PI / 2;

			const corners = d3.range(corner_count).map(side => {
				const radian = side * ((Math.PI * 2) / corner_count);
				const x = Math.cos(radian + offset) * this.#radius_outer;
				const y = Math.sin(radian + offset) * this.#radius_outer;
				return [x, y];
			});

			return corners.map((corner, index) => {
				const prev = index === 0 ? corners[corners.length - 1] : corners[index - 1];
				const curr = corner;
				const next = index <= corners.length - 2 ? corners[index + 1] : corners[0];

				return {
					start: getPositionOnLine(prev, curr, 0.5),
					start_curve: getPositionOnLine(prev, curr, 1 - corner_radius),
					handle_1: getPositionOnLine(prev, curr, 1 - handle_distance111),
					handle_2: getPositionOnLine(curr, next, handle_distance111),
					end_curve: getPositionOnLine(curr, next, corner_radius)
				};
			});
		};

		const corners = getCorners();

		let path = `M${corners[0].start}`;
		path += corners.map(c => `L${c.start}L${c.start_curve}C${c.handle_1} ${c.handle_2} ${c.end_curve}`);
		path += 'Z';

		return path.replaceAll(',', ' ');
	}

	/**
	 * Get text width using canvas measuring.
	 *
	 * @param {string} text
	 * @param {number} size
	 * @param {string} font_family
	 *
	 * @returns {number}
	 */
	#getMeasuredTextWidth(text, size, font_family) {
		const canvas = document.createElement('canvas');
		const context = canvas.getContext('2d');

		context.font = `${size}px ${font_family}`;

		return context.measureText(text).width;
	}
}
