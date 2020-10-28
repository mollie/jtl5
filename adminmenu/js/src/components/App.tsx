import React from 'react';
import Header from './Header/index';
import Tabs from './../../../tabs';
import TabHeader from './../components/Tab/Header';
import Footer from './Footer';

const App = () => {
    return (
        <div id={'App'} className={"p-3"}>
            <Header/>
            <TabHeader tabs={Tabs}/>
            <Footer/>
        </div>
    );
}

export default App;
