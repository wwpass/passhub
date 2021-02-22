import state from "./state";


const openNodes = new Set();

function toggleDetails(e) {
    console.log(e.target);
    const id = e.target.querySelector('summary').id;

    if(e.target.open) {
        openNodes.add(id);
    } else {
        openNodes.delete(id);
    }
}

/*
flex: 0 1 auto;
flex-grow: 0;
flex-shrink: 1;
flex-basis: auto;
white-space: nowrap;
overflow: hidden;

            div.style.display='flex';
            div.style.position='relative';
            let inner = `<div style:'flex:0 0' >${folder.icon}</div>`;
            inner+= `<div style='flex: 1 1; white-space: nowrap; overflow:hidden' >${folder.text}</div>`;
            div.innerHTML = inner;


*/

function treeView(container, folders) {
    for( let f = 0; f < folders.length; f++) {
        const folder = folders[f];
        if((folder.children == undefined) || folder.children.length < 1) {
            const div = document.createElement('div');
            div.classList.add('leaf')
            if(folder.isSafe) {
                div.classList.add('safe');
            }

            div.style.position = 'relative';
            div.style.overflow='hidden';
            div.style.whiteSpace='nowrap';
            div.innerHTML = `${folder.icon}${folder.text}`;
            if( (folder.id == state.ActiveFolder) || (folder.id == state.currentSafe.id) ) {
                div.classList.add('active-folder')
            }
            div.id = folder.id;
            container.appendChild(div);
        } else {
            const details = document.createElement('details');
            details.style.position = 'relative';
            const summary = document.createElement('summary');
/*
            const div = document.createElement('div');
            div.style.display='flex';

            let inner = `<div style:'flex:0 0' >${folder.icon}</div>`;
            inner+= `<div style='flex: 1 1; white-space: nowrap; overflow:hidden' >${folder.text}</div>`;
            div.innerHTML = inner;
            summary.appendChild(div);
*/
            // summary.style.textOverflow='clip';
            summary.style.overflow='hidden';
            summary.style.whiteSpace='nowrap';
            summary.innerHTML = `${folder.icon}${folder.text}`;

            summary.id = folder.id;
            if(folder.isSafe) {
                summary.classList.add('safe');
            }
            if( (folder.id == state.ActiveFolder) || (folder.id == state.currentSafe.id) ) {
                summary.classList.add('active-folder')
            }
            container.appendChild(details);
            details.appendChild(summary);

            if(openNodes.has(folder.id)) {
                details.open = true;
            }
            details.addEventListener('toggle', toggleDetails );

            treeView(details, folder.children);
        }
    }
} 

export default  treeView;
