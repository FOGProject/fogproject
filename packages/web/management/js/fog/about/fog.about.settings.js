(function($) {
  var base = '../management/index.php?node=' + Common.node,
    saveAction = base + '&sub=' + Common.sub,
    contentUrl = base + '&sub=settingsContent',
    cacheAction = base + '&sub=',
    method = 'post',
    activeCat = null;

  // Relayout sliders that live in a now-visible panel. bootstrap-slider
  // measures width at init time, so any slider built while its panel was
  // hidden renders at zero width until relaid out.
  function relayoutVisibleSliders() {
    $('#settings-content .slider:visible').each(function() {
      try {
        $(this).slider('relayout');
      } catch (e) {}
    });
  }

  function isMobile() {
    return window.matchMedia
      && window.matchMedia('(max-width: 767px)').matches;
  }

  // Select a category. On desktop this is the single visible panel; on mobile
  // it is the expanded accordion section. Visibility itself is driven by CSS
  // off the .active class + the .searching mode flag. Falls back to the first
  // category when the requested one is gone (e.g. after a body reload).
  function showCategory(cat) {
    var $navItems = $('#settings-nav li'),
      matched = false;
    $navItems.removeClass('active');
    $navItems.each(function() {
      if ($(this).find('a').attr('data-cat') === cat) {
        $(this).addClass('active');
        matched = true;
      }
    });
    if (!matched) {
      var $first = $navItems.first();
      cat = $first.find('a').attr('data-cat');
      $first.addClass('active');
    }
    activeCat = cat;
    $('.settings-layout').removeClass('searching');
    $('.settings-panel').each(function() {
      $(this).toggleClass('active', $(this).attr('data-cat') === cat);
    });
    $('.settings-noresults').addClass('d-none');
    relayoutVisibleSliders();
  }

  // Filter rows across every category by a search term. Empty term restores
  // single-category nav mode.
  function applySearch(term) {
    term = (term || '').trim().toLowerCase();
    if (term === '') {
      $('.settings-row').removeClass('d-none');
      $('.settings-panel').removeClass('d-none');
      showCategory(activeCat);
      return;
    }
    $('.settings-layout').addClass('searching');
    $('#settings-nav li').removeClass('active');
    var anyVisible = false;
    $('.settings-panel').each(function() {
      var $panel = $(this),
        anyRow = false;
      $panel.find('.settings-row').each(function() {
        var hay = $(this).attr('data-search') || '';
        if (hay.indexOf(term) !== -1) {
          $(this).removeClass('d-none');
          anyRow = true;
        } else {
          $(this).addClass('d-none');
        }
      });
      $panel.toggleClass('d-none', !anyRow);
      if (anyRow) {
        anyVisible = true;
      }
    });
    $('.settings-noresults').toggleClass('d-none', anyVisible);
    relayoutVisibleSliders();
  }

  // Reload the settings body fragment after a save so derived values and any
  // server-side normalization are reflected, then restore the view state.
  function reloadSettings() {
    $.get(contentUrl, function(html) {
      $('#settings-content').html(html);
      initSettings();
      var term = $('#settings-search').val();
      if (term && term.trim() !== '') {
        applySearch(term);
      } else {
        showCategory(activeCat);
      }
    });
  }

  // Settings whose row carries the .settings-refresh-note icon only take
  // effect after a full page reload (see fogconfigurationpage.page.php). On a
  // successful save, follow the server's "saved" toast with a reminder so the
  // user isn't left wondering why nothing changed. Call before reloadSettings()
  // while the source element is still in the DOM.
  function maybeNudgeRefresh(el) {
    if (!$(el).closest('.settings-row').find('.settings-refresh-note').length) {
      return;
    }
    $.notify(
      'Reload required',
      'This setting takes effect after you reload the page. A hard refresh '
        + '(Ctrl+F5, or Cmd+Shift+R) may be required.',
      'info'
    );
  }

  function saveCheckbox(el, val) {
    var opts = {};
    opts[$(el).attr('name')] = val;
    $.apiCall(method, saveAction, opts, function(err) {
      if (err) {
        return;
      }
      maybeNudgeRefresh(el);
      reloadSettings();
    });
  }

  // Initialize plugins and (re)bind save handlers for the freshly rendered
  // body. Safe to call again after a fragment reload.
  function initSettings() {
    $('#settings-content .jscolor').each(function() {
      var color = $('#FOG_COMPANY_COLOR').val();
      new jscolor(this, {'value': color});
    });
    $('#settings-content .slider').slider();
    $('#settings-content [data-bs-toggle="tooltip"]').tooltip();
    $('#settings-content :password').each(function() {
      if (!$(this).prev().hasClass('input-group-text')) {
        $(this).before(
          '<span class="input-group-text">'
          + '<i class="fa fa-eye-slash fogpasswordeye"></i></span>'
        );
      }
    });
    $('#settings-content .input-group,#settings-content .form-control').css({
      width: '100%'
    });
    Common.iCheck('#settings-content :input');

    $('#settings-content .resettoken').off('click.fogset')
      .on('click.fogset', function(e) {
        e.preventDefault();
        Pace.ignore(function() {
          $.ajax({
            url: '../status/newtoken.php',
            dataType: 'json',
            success: function(data) {
              $('.token').val(data);
              var opts = $('.token').serialize();
              $.apiCall(method, saveAction, opts, function(err) {
                if (err) {
                  return;
                }
                reloadSettings();
              });
            },
            error: function() {}
          });
        });
      });

    $('#settings-content :input').each(function() {
      var ev = $(this).hasClass('slider') ? 'slideStop' : 'change';
      $(this).off(ev + '.fogset').on(ev + '.fogset', function(e) {
        e.preventDefault();
        var el = this,
          opts = new FormData();
        opts.append(el.name, el.files ? el.files[0] : el.value);
        $.apiCall(method, saveAction, opts, function(err) {
          if (err) {
            return;
          }
          maybeNudgeRefresh(el);
          reloadSettings();
        }, false);
      });
    });
    $('#settings-content :checkbox')
      .off('change.fogset')
      .on('change.fogset', function(e) {
        if (!e.target.checked) { return; }
        saveCheckbox(this, 1);
      })
      .on('change.fogset', function(e) {
        if (e.target.checked) { return; }
        saveCheckbox(this, 0);
      });
  }

  // Persistent handlers (chrome that survives body reloads).
  $('#settings-content').on('click', '#settings-nav a', function(e) {
    e.preventDefault();
    $('#settings-search').val('');
    $('.settings-row').removeClass('d-none');
    showCategory($(this).attr('data-cat'));
  });
  // Mobile accordion: the panel title toggles its own section. No-op on
  // desktop (the side-nav drives selection there) and while searching.
  $('#settings-content').on('click', '.settings-panel-title', function() {
    if (!isMobile() || $('#settings-search').val().trim() !== '') {
      return;
    }
    var cat = $(this).attr('data-cat');
    if (cat === activeCat) {
      activeCat = null;
      $('.settings-panel').removeClass('active');
      $('#settings-nav li').removeClass('active');
      return;
    }
    showCategory(cat);
  });
  $('#settings-search').on('input', function() {
    applySearch($(this).val());
  });
  $('#settings-search-clear').on('click', function(e) {
    e.preventDefault();
    $('#settings-search').val('');
    applySearch('');
  });
  $('#settings-cache-flush').off('click').on('click', function(e) {
    e.preventDefault();
    $.apiCall('post', cacheAction + 'cacheFlushPost', {});
  });
  $('#settings-cache-refresh').off('click').on('click', function(e) {
    e.preventDefault();
    $.apiCall('post', cacheAction + 'cacheRefreshPost', {});
  });

  initSettings();
  showCategory(null);
  if (Common.search && Common.search.length > 0) {
    $('#settings-search').val(Common.search);
    applySearch(Common.search);
  }
})(jQuery);
