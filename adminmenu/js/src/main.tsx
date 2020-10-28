import React from 'react';
import ReactDOM from 'react-dom';
// @ts-ignore
import App from "@AdminMenuSrc/components/App";
import '@CSS/adminmenu.css'

declare global {
    interface Window {
        FavAdd: Element | null;
        ContentWrapper: Element | null;
    }
}

window.ContentWrapper = document.querySelector('#content_wrapper');
window.FavAdd = document.querySelector('#fav-add');
if (window.ContentWrapper) {
    window.ContentWrapper.classList.add('position-relative');
}

ReactDOM.render(<React.StrictMode>
    <App/>
</React.StrictMode>, document.querySelector("#root_react"));
