(function ($) {
  Drupal.behaviors.horizontalScroller = {
    attach: function (context, settings) {
      // Progress Summary
      var root_element = $('.wmd-view');
      if (root_element.length > 0) {

        $(".wmd-view").doubleScroll();

        // makes it still work on resize
        $(window).resize(function(){
          $(".suwala-doubleScroll-scroll-wrapper").css('width', $(window).width()+'px');
        });
        /*$('.scroll-pane').jScrollPane();*/
       /* $(".scroll-div1").css('width', $(".scroll-div2 table").width()+'px');

        $(".wmd-view-topscroll").scroll(function () {
          $(".wmd-view table").scrollLeft($(".wmd-view-topscroll").scrollLeft());
        });
        $(".wmd-view table").scroll(function () {
          $(".wmd-view-topscroll").scrollLeft($(".wmd-view table").scrollLeft());
        });*/

        // Progress Summary
        var root_element2 = $('.wmd-view');
        if(root_element2.length > 0){
          //$('.group-individual-results table').doubleScroll();

          let mouseDown = false;
          let startX, scrollLeft;
          const slider = document.querySelector('.wmd-view');

          const startDragging = (e) => {
            mouseDown = true;
            startX = e.pageX - slider.offsetLeft;
            scrollLeft = slider.scrollLeft;
          }

          const stopDragging = (e) => {
            mouseDown = false;
          }

          const move = (e) => {
            e.preventDefault();
            if(!mouseDown) { return; }
            const x = e.pageX - slider.offsetLeft;
            const scroll = x - startX;
            slider.scrollLeft = scrollLeft - scroll;
          }

// Add the event listeners
          slider.addEventListener('mousemove', move, false);
          slider.addEventListener('mousedown', startDragging, false);
          slider.addEventListener('mouseup', stopDragging, false);
          slider.addEventListener('mouseleave', stopDragging, false);
        }
      }
    }
  };
})(jQuery);
