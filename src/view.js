/**
 * Use this file for JavaScript code that you want to run in the front-end
 * on posts/pages that contain this block.
 *
 * When this file is defined as the value of the `viewScript` property
 * in `block.json` it will be enqueued on the front end of the site.
 *
 * Example:
 *
 * ```js
 * {
 *   "viewScript": "file:./view.js"
 * }
 * ```
 *
 * If you're not making any changes to this file because your project doesn't need any
 * JavaScript running in the front-end, then you should delete this file and remove
 * the `viewScript` property from `block.json`.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-metadata/#view-script
 */

document.addEventListener( "DOMContentLoaded", async () => {

  const cardObserver = new IntersectionObserver( function( cards ) {
        
    cards.forEach( function( card ) {
    
      var content = card.target;
      
      if ( card.isIntersecting ) {
        content.classList.remove( 'hidden' );
        cardObserver.unobserve( content );
      }
      
    });

  } );

  try {
    await fetch( '/wp-json/eightyfour/v1/json' )
    .then( response => {
      if ( 200 === response.status ) {
        return response.json();
      } else {
        throw response;
      }
    } )
    .then( result => {

      if ( result.length ) {

        document.querySelectorAll( '.wp-block-eightyfour-sideload-json-posts' ).forEach( ( wrapper ) => {

          let counter = 0;

          wrapper.innerHTML = '';
          wrapper.classList.add( 'initialized' );

          result.forEach( ( result ) => {

            const template = document.createElement( 'TEMPLATE' );
            template.innerHTML = result;

            const card = template.content.firstElementChild

            if ( counter >= 5 ) {
              card.classList.add( 'hidden' );
            }
            wrapper.appendChild( card );

            // No need for this with pagination... Could be used to build out lazy-loading instead.
            // cardObserver.observe( card );

            counter++;

          } );

          if ( 10 < counter ) {

            // Messy, but ok for spec work...
            const blockWrapper = document.createElement( 'DIV' );
            blockWrapper.classList.add( 'wp-block-eightyfour-sideload-json-posts-wrapper' );

            const paginationContainer = document.createElement( 'NAV' );
            paginationContainer.classList.add( 'eightyfour-pagination-container' );

            const paginationNumbers = document.createElement( 'DIV' );
            paginationNumbers.classList.add( 'eightyfour-pagination-numbers' );
            paginationContainer.appendChild( paginationNumbers );

            const prevButton = document.createElement( 'BUTTON' );
            prevButton.classList.add( 'eightyfour-pagination-button' );
            prevButton.textContent = 'Previous';
            paginationContainer.appendChild( prevButton );

            const nextButton = document.createElement( 'BUTTON' );
            nextButton.classList.add( 'eightyfour-pagination-button' );
            nextButton.textContent = 'Next';
            paginationContainer.appendChild( nextButton );

            const paginatedList = wrapper;
            const listItems = paginatedList.children;

            paginatedList.parentNode.insertBefore( blockWrapper, paginatedList );

            blockWrapper.appendChild( paginatedList );
            blockWrapper.appendChild( paginationContainer );
            
            const paginationLimit = 10;
            const pageCount = Math.ceil( listItems.length / paginationLimit );
            let currentPage = 1;
            
            const disableButton = ( button ) => {
              button.disabled = true;
            };
            
            const enableButton = (button) => {
              button.disabled = false;
            };
            
            const handlePageButtonsStatus = () => {
              if (currentPage === 1) {
                disableButton(prevButton);
              } else {
                enableButton(prevButton);
              }
            
              if (pageCount === currentPage) {
                disableButton(nextButton);
              } else {
                enableButton(nextButton);
              }
            };
            
            const handleActivePageNumber = () => {
              paginationNumbers.querySelectorAll( ".eightyfour-pagination-number" ).forEach((button) => {
                button.classList.remove("active");
                const pageIndex = Number(button.getAttribute("page-index"));
                if (pageIndex == currentPage) {
                  button.classList.add("active");
                }
              });
            };
            
            const appendPageNumber = (index) => {
              const pageNumber = document.createElement("button");
              pageNumber.className = "eightyfour-pagination-number";
              pageNumber.innerHTML = index;
              pageNumber.setAttribute("page-index", index);
              pageNumber.setAttribute("aria-label", "Page " + index);
            
              paginationNumbers.appendChild(pageNumber);
            };
            
            const getPaginationNumbers = () => {
              for (let i = 1; i <= pageCount; i++) {
                appendPageNumber(i);
              }
            };
            
            const setCurrentPage = (pageNum) => {
              currentPage = pageNum;
            
              handleActivePageNumber();
              handlePageButtonsStatus();
              
              const prevRange = (pageNum - 1) * paginationLimit;
              const currRange = pageNum * paginationLimit;

              /* eslint-disable no-console */
              // console.log( 'paginatedList', paginatedList );
              // console.log( 'listItems', listItems );
              /* eslint-enable no-console */
            
              Array.from( listItems ).forEach((item, index) => {
                item.classList.add("hidden");
                if (index >= prevRange && index < currRange) {
                  item.classList.remove("hidden");
                }
              });
            };
            
            getPaginationNumbers();
            setCurrentPage(1);
          
            prevButton.addEventListener("click", () => {
              setCurrentPage(currentPage - 1);
            });
          
            nextButton.addEventListener("click", () => {
              setCurrentPage(currentPage + 1);
            });
          
            paginationNumbers.querySelectorAll( ".eightyfour-pagination-number" ).forEach((button) => {
              const pageIndex = Number(button.getAttribute("page-index"));
          
              if (pageIndex) {
                button.addEventListener("click", () => {
                  setCurrentPage(pageIndex);
                });
              }
            });

          }

        } );
      }
  
    } );
  } catch ( e ) {

    // Do some error checking, I guess...

    /* eslint-disable no-console */
    // console.log( 'exception', e );
    /* eslint-enable no-console */
  }

} );

