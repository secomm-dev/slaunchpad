define([
	'angular',
	'Magezon_Core/js/mage/browser'
], function(angular, urlBuilder) {

	return {
		controller: function($scope, $rootScope) {
			$scope.openFileManager =  function() {
	            var targetId = $scope.id;
				// var baseUrl = ($rootScope.builderConfig && $rootScope.builderConfig.mediaGalleryUrl) 
                //               ? $rootScope.builderConfig.mediaGalleryUrl 
                //               : urlBuilder.build('media_gallery/index/index');

                // var openDialogUrl = baseUrl;
                
                // if (openDialogUrl.indexOf('?') !== -1) {
                //     openDialogUrl += '&target_element_id=' + targetId + '&isAjax=true';
                // } else {
                //     if (openDialogUrl.slice(-1) !== '/') openDialogUrl += '/';
                //     openDialogUrl += 'target_element_id/' + targetId + '/?isAjax=true';
                // }


				var formKey = window.FORM_KEY || '';
				var mediaGalleryUrl = $rootScope.builderConfig && $rootScope.builderConfig.mediaGalleryUrl ?
					$rootScope.builderConfig.mediaGalleryUrl : '';
				var fileManagerUrl = $rootScope.builderConfig && $rootScope.builderConfig.fileManagerUrl ?
					$rootScope.builderConfig.fileManagerUrl : '';
				var baseUrl = $rootScope.builderConfig && $rootScope.builderConfig.baseUrl ?
					$rootScope.builderConfig.baseUrl : (window.location.origin + '/');
				var openDialogUrl = mediaGalleryUrl ?
					mediaGalleryUrl.replace(encodeURIComponent('UID'), encodeURIComponent(targetId)).replace('UID', encodeURIComponent(targetId)) :
					(fileManagerUrl ?
						fileManagerUrl.replace(encodeURIComponent('UID'), encodeURIComponent(targetId)).replace('UID', encodeURIComponent(targetId)) :
						baseUrl + 'media_gallery/index/index/target_element_id/' + encodeURIComponent(targetId) + '/');

				openDialogUrl += (openDialogUrl.indexOf('?') === -1 ? '?' : '&') + 'isAjax=true';
				if (formKey && openDialogUrl.indexOf('form_key=') === -1) {
					openDialogUrl += '&form_key=' + encodeURIComponent(formKey);
				}

				MgzMediabrowserUtility.openDialog(openDialogUrl, null, null, 'Media Gallery...', {
                    targetElementId: targetId
                });
	        }
			
			$scope.$watch('model[options.key]', function(newVal) {
				if (newVal && newVal.indexOf('___directive') !== -1) {
					var parts = newVal.split('___directive/');
					if (parts.length > 1) {
						var encoded = parts[1].split('/')[0].replace(/~~$/, '');
						try {
							var cleanEncoded = encoded.replace(/~+$/, '');
							cleanEncoded = cleanEncoded.replace(/-/g, '+').replace(/_/g, '/');
							while (cleanEncoded.length % 4 !== 0) {
								cleanEncoded += '=';
							}

							var decoded = atob(cleanEncoded);
							var match = decoded.match(/url="(.+?)"/);
							if (match && match[1]) {
								var cleanPath = match[1];
								if (cleanPath.indexOf('.renditions/') !== -1) {
									cleanPath = cleanPath.replace('.renditions/', '');
								}
								$scope.model[$scope.options.key] = cleanPath;
							}
						} catch (error) {
							console.error("Couldn't decode that directive");
						}
					}
				}
			});

	        $scope.getSrc = function() {
				var value = $scope.model[$scope.options.key];
	        	if (value) {
					if (value.indexOf('http') === 0 || value.indexOf('___directive') !== -1) {
						return value;
					}
					return $rootScope.builderConfig.mediaUrl + value;
				}
	        }
		}
	}
})