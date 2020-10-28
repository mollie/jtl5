import React, {useState} from 'react'
import cls from './Header.module.scss'
import {FontAwesomeIcon} from '@fortawesome/react-fontawesome'
import {faCog, faStar, faTimes} from '@fortawesome/pro-solid-svg-icons'
import Settings from '../Settings'
import usePluginInfo from "../../hooks/usePluginInfo";

// Runder Schatten:
// http://jsfiddle.net/5a0y39be/

const Header = () => {

    const [isSettingsOpen, setSettingsOpen] = useState(false);
    const settingsAvailable = !!document.querySelector('.settings-content form');
    const pluginInfo = usePluginInfo();

    return <div>
        <div className={['container', cls.pluginHeader].join(' ')}>

            <div className={cls.pluginAvatar}>
                <img style={{maxWidth: '75px'}} src={`https://cdn.webstollen.de/plugins/${pluginInfo.pluginID.replace('5_', '_')}_ws.svg`}/>
            </div>
            <div className={cls.pluginTopBar}>
                <div className={cls.pluginName}>
                    {pluginInfo?.name}
                </div>
                <div className={cls.pluginActions}>
                    {settingsAvailable && <a onClick={() => setSettingsOpen(prev => !prev)} className={'pr-2'}>
                        <FontAwesomeIcon icon={isSettingsOpen ? faTimes : faCog} size={'lg'}/>
                    </a>}

                    <a href={'#'}>
                        <FontAwesomeIcon color={pluginInfo.isFav ? 'black' : '#c0c0c0'} icon={faStar} size={'lg'}/>
                    </a>
                </div>
            </div>
        </div>
        <div className={['container', cls.pluginInfoBar].join(' ')}>
            <div className={cls.infoBar}>
                <object data={'//lic.dash.bar/info/licence?' + pluginInfo.svg}
                        type={'image/svg+xml'}>
                    <img src={'//lic.dash.bar/info/licence.png?' + pluginInfo.svg} height={20}/>
                </object>
                <object data={'//lic.dash.bar/info/version?' + pluginInfo.svg}
                        type={'image/svg+xml'}>
                    <img src={'//lic.dash.bar/info/version.png?' + pluginInfo.svg} height={20}/>
                </object>
                <object data={'//lic.dash.bar/info/help?' + pluginInfo.svg}
                        type={'image/svg+xml'}>
                    <img src={'//lic.dash.bar/info/help.png?' + pluginInfo.svg} height={20}/>
                </object>
            </div>
        </div>

        {isSettingsOpen && <Settings pluginInfo={pluginInfo} onClose={() => setSettingsOpen(false)}/>}

    </div>;
}

export default Header;