$(document).ready(function(){
    var $treeContainer = $('#tree');

    loadNodes($treeContainer);

    $(document).on('click', '#tree ul li > span.fa-angle-right', function(){
        loadNodes($(this).parent());
        $(this).removeClass('fa-angle-right');
        $(this).addClass('fa-angle-down');
        setCookie('node_' + $(this).parent().data('id'), 'opened');
    });
    $(document).on('click', '#tree ul li > span.fa-angle-down', function(){
        $(this).parent().find('ul').remove();
        $(this).removeClass('fa-angle-down');
        $(this).addClass('fa-angle-right');
        setCookie('node_' + $(this).parent().data('id'), 'closed');
    });

    function loadNodes($node, callback){
        var url = $treeContainer.data('url');
        if($node.data('id')){
            url += '/' + $node.data('id');
        }
        $.ajax({
            url: url,
            success: function(response){
                if(response.data){
                    $ul = $('<ul></ul>');
                    $node.append($ul);
                    for(var i in response.data){
                        var nodeData = response.data[i];
                        var $nodeItem = getTreeItem(nodeData);
                        $ul.append($nodeItem);
                        if(getCookie('node_' + nodeData.id) == 'opened'){
                            $nodeItem.children('span.fa-angle-right').click();
                        }
                    }
                    if(typeof(callback) == 'function'){
                        callback();
                    }
                }else{
                    $treeContainer.prepend(showMessage(rootErrorMessage, 'alert-warning'));
                }
            }
        })
    }

    function showMessage(text, classname){
        $treeContainer.find('.alert').remove();
        var $message = $('<div role="alert">' +
            '<button type="button" class="close" data-dismiss="alert">' +
            '<span aria-hidden="true">&times;</span>&nbsp;' +
            '</button>' +
            '<span class="message-content"></span>' +
            '</div>');
        $message.find('.message-content').text(text);
        $message.attr('class', 'alert ' + classname + ' alert-dismissible');
        return $message;
    }

    function checkTreeRoot(){
        if($rootSelect.val()){
            $treeContainer.data('id', $rootSelect.val());
            setCookie('sip_root_id', $rootSelect.val());
            loadNodes($treeContainer);
        }else{
            $treeContainer.prepend(showMessage(rootErrorMessage, 'alert-warning'));
        }
    }

    function getTreeItem(nodeData){
        var $node = $('<li data-id="' + nodeData.id + '" data-move-url="' + nodeData.moveUrl + '"></li>');

        $node.attr('id', 'sip-tree-node-' + nodeData.id);
        $node.data('url', $treeContainer.data('url') + '/' + nodeData.id);
        $node.append('<div class="hover-sort-top"></div>');
        $node.append('<div class="hover-sort-bottom"></div>');
        $node.append('<div class="hover-drop"></div>');
        $node.append('<a class="item-title" href="' + nodeData.editUrl + '">' + nodeData.title + '</a><div class="btn-group">');
        $node.find('.btn-group').append('<a href="' + nodeData.addChildUrl + '" class="btn btn-default btn-xs"><span class="glyphicon glyphicon-plus"></span></a>');
        $node.find('.btn-group').append('<a href="' + nodeData.deleteUrl + '" class="btn btn-default btn-xs"><span class="glyphicon glyphicon-remove"></span></a>');
        if(nodeData.children.length){
            $node.find('.item-title').before('<span class="fa fa-angle-right"></span>');
        }
        $node.draggable({
            revert: true,
            appendTo: '#tree > ul',
            start: function(event, ui){
                var $dragged = $(this);
            }
        });
        $node.find('.hover-drop').droppable({
            hoverClass: 'active',
            drop: function(event, ui){
                var $dragged = ui.draggable;
                var $dropped = $(this);
                var $ulList = $dragged.parent();
                $dragged.draggable('option', 'revert', false);
                if($dropped.parent().children('ul').length){
                    $dropped.parent().children('ul').append($dragged);
                    $dragged.attr('style', '');
                    $dragged.draggable('option', 'revert', true);
                    if(!$dropped.parent().children('.fa-angle-right').length && !$dropped.parent().children('.fa-angle-down').length){
                        $dropped.after('<span class="fa fa-angle-down"></span>');
                    }
                }else{
                    $dragged.hide();
                    $.ajax({
                        url: $dragged.data('move-url') + '/' + $dropped.parent().data('id') + '/append',
                        success: function(moveResponse){
                            if(moveResponse.data){
                                loadNodes($dropped.parent(), function(){
                                    $dragged.remove();
                                    if(!$dropped.parent().children('.fa-angle-right').length && !$dropped.parent().children('.fa-angle-down').length){
                                        $dropped.after('<span class="fa fa-angle-down"></span>');
                                    }
                                    if(!$ulList.children('li').length){
                                        $ulList.parent().children('span.fa-angle-down').remove();
                                        $ulList.remove();
                                    }

                                    setCookie('node_' + $dropped.parent().data('id'), 'opened');
                                });
                            }else{
                                if(moveResponse.error){
                                    $treeContainer.prepend(showMessage(moveResponse.error, 'alert-warning'));
                                }
                                $dragged.attr('style', '');
                                $dragged.show();
                                $dragged.draggable('option', 'revert', true);
                            }
                        }
                    });
                }
                if(!$ulList.children('li').length){
                    $ulList.parent().children('span.fa-angle-down').remove();
                    $ulList.remove();
                }
            }
        });
        $node.find('.hover-sort-top, .hover-sort-bottom').droppable({
            hoverClass: 'active',
            drop: function(event, ui){
                var $dragged = ui.draggable;
                var $dropped = $(this);
                var $ulList = $dragged.parent();
                $dragged.draggable('option', 'revert', false);
                if($dropped.hasClass('hover-sort-top')){
                    $.ajax({
                        url: $dragged.data('move-url') + '/' + $dropped.parent().data('id') + '/before',
                        success: function(moveResponse){
                            $dropped.parent().before($dragged);
                        }
                    });
                }else if($dropped.hasClass('hover-sort-bottom')){
                    $.ajax({
                        url: $dragged.data('move-url') + '/' + $dropped.parent().data('id') + '/after',
                        success: function(moveResponse){
                            $dropped.parent().after($dragged);
                        }
                    });
                }
                $dragged.draggable('option', 'revert', true);
                if(!$ulList.find('li').length){
                    $ulList.parent().children('span.fa-angle-down').remove();
                    $ulList.remove();
                }
            }
        });
        return $node;
    }
});

function setCookie(key, value) {
    var expires = new Date();
    expires.setTime(expires.getTime() + (1 * 24 * 60 * 60 * 1000));
    document.cookie = key + '=' + value + ';expires=' + expires.toUTCString();
}

function getCookie(key) {
    var keyValue = document.cookie.match('(^|;) ?' + key + '=([^;]*)(;|$)');
    return keyValue ? keyValue[2] : null;
}