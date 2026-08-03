define([
	'jquery',
	'angular'
], function($, angular) {

	var directive = function(magezonBuilderUrl) {
		return {
			replace: true,
			templateUrl: function(elem) {
				return magezonBuilderUrl.getTemplateUrl(elem, 'Magezon_Builder/js/templates/builder/element/single-image.html');
			},
			controller: function($scope, $controller) {
				var parent = $controller('baseController', {$scope: $scope});
				angular.extend(this, parent);

				var resolveImageUrl = function(image) {
					if (!image) {
						return '';
					}
					var src = '';

					if (image.indexOf('http') === 0 || image.indexOf('___directive') !== -1) {
						src = image;
					} else {
						src = magezonBuilderUrl.getImageUrl(image);
					}

					return magezonBuilderUrl.getImageUrl(image);
				}

				$scope.getSrc = function() {
					var src = resolveImageUrl($scope.element.image);

					if ($scope.element.source === 'external_link' && $scope.element.custom_src) {
						src = $scope.element.custom_src;
					}

					return src;
				}

				$scope.getHoverSrc = function() {
					return resolveImageUrl($scope.element.hover_image);
				}
			},
			link: function(scope, element) {

				var inner = element.find('.mgz-single-image-inner');
				var eventNs = '.mgzSingleImageHover';
				var preloadedHoverSrc = '';

				var getImage = function() {
					return inner.find('img').first();
				}

				var lockImageSize = function(img) {
					if (!img.length || img.data('mgzTempSizeLock')) {
						return;
					}

					var hasConfiguredSize = !!(
						scope.element.image_width ||
						scope.element.image_height ||
						img.attr('width') ||
						img.attr('height')
					);
					if (!hasConfiguredSize) {
						var width = img.outerWidth();
						var height = img.outerHeight();
						if (width > 0 && height > 0) {
							img.css({
								width: width + 'px',
								height: height + 'px'
							});
							img.data('mgzTempSizeLock', true);
						}
					}
				};

				var unlockImageSize = function(img) {
					if (!img.length || !img.data('mgzTempSizeLock')) {
						return;
					}

					img.css({
						width: '',
						height: ''
					});
					img.removeData('mgzTempSizeLock');
				};

				var preloadHoverImage = function(src) {
					if (!src || src === preloadedHoverSrc) {
						return;
					}

					var preloader = new Image();
					preloader.src = src;
					preloadedHoverSrc = src;
				};

				var showHoverImage = function() {
					var img = getImage();
					if (!img.length) {
						return;
					}

					var hoverSrc = scope.getHoverSrc();
					if (!hoverSrc) {
						return;
					}

					preloadHoverImage(hoverSrc);
					var currentSrc = img.attr('src');
					lockImageSize(img);
					if (currentSrc !== hoverSrc) {
						img.attr('src', hoverSrc);
					}
				};

				var showMainImage = function() {
					var img = getImage();
					if (!img.length) {
						return;
					}

					var mainSrc = scope.getSrc();
					if (mainSrc && img.attr('src') !== mainSrc) {
						img.attr('src', mainSrc);
					}

					unlockImageSize(img);
				};

				inner.on('mouseenter' + eventNs, showHoverImage);
				inner.on('mouseleave' + eventNs, showMainImage);

				scope.$on('$destroy', function () {
					inner.off(eventNs);
				});
			},
			controllerAs: 'mgz'
		}
	}

	return directive;
});