import React, {useState} from 'react';
import {Tab} from '../../../../../tabs';
import cls from './Header.module.scss';
import Wrapper from './../Wrapper';

type Props = {
    tabs: Tab[]
}

const Header = (props: Props) => {

    const [activeTab, setActiveTab] = useState(0);
    const [activeTabContent, setActiveTabContent] = useState<React.ReactNode | null>(null);

    //useEffect(() => setActiveTabContent(props.tabs[activeTab].component), [activeTab])

    return <>
        <div className={'flex flex-row justify-center items-center'}>
            <div className={cls.tabHeader}>
                {props.tabs && props.tabs && props.tabs
                    .map((tab, i) => <a key={i} onClick={() => setActiveTab(i)}
                                            className={[i === activeTab ? cls.active : null, cls.tabHeaderItem].join(' ')}>
                        {tab.title}
                    </a>)}
            </div>
        </div>
        <div className={cls.tabContent}>


                {props.tabs.map((tab, i) => <Wrapper
                    key={i}
                    isDashboard={!!tab.isDashboard}
                    active={activeTab === i}>
                    {tab.component ? React.createElement(tab.component) : null}
                </Wrapper>)}


        </div>
    </>
}

export default Header;