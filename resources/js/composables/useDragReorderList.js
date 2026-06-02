import { ref } from 'vue';

/**
 * Arrastar e soltar para reordenar listas (HTML5 DnD).
 *
 * @param {(orderedIds: Array<number|string>) => void} onCommit
 */
export function useDragReorderList(onCommit) {
    const draggingId = ref(null);
    const dragOverId = ref(null);

    function onDragStart(e, id) {
        draggingId.value = id;
        e.dataTransfer.effectAllowed = 'move';
        try {
            e.dataTransfer.setData('text/plain', String(id));
        } catch (_) {
            /* ignore */
        }
    }

    function onDragEnd() {
        draggingId.value = null;
        dragOverId.value = null;
    }

    function onDragOver(e, targetId) {
        e.preventDefault();
        if (draggingId.value == null || draggingId.value === targetId) {
            return;
        }
        dragOverId.value = targetId;
        e.dataTransfer.dropEffect = 'move';
    }

    function onDragLeave(targetId) {
        if (dragOverId.value === targetId) {
            dragOverId.value = null;
        }
    }

    function reorderItems(items, fromId, toId) {
        if (fromId == toId) {
            return items;
        }
        const list = [...items];
        const fromIdx = list.findIndex((x) => x.id == fromId);
        const toIdx = list.findIndex((x) => x.id == toId);
        if (fromIdx < 0 || toIdx < 0) {
            return items;
        }
        const [moved] = list.splice(fromIdx, 1);
        list.splice(toIdx, 0, moved);

        return list;
    }

    function onDrop(e, items, targetId) {
        e.preventDefault();
        const fromId = draggingId.value;
        dragOverId.value = null;
        draggingId.value = null;
        if (fromId == null || fromId === targetId) {
            return;
        }
        const reordered = reorderItems(items, fromId, targetId);
        onCommit(reordered.map((x) => x.id));
    }

    function isDragging(id) {
        return draggingId.value != null && draggingId.value == id;
    }

    function isDragOver(id) {
        return (
            dragOverId.value != null &&
            dragOverId.value == id &&
            draggingId.value != null &&
            draggingId.value != id
        );
    }

    return {
        draggingId,
        onDragStart,
        onDragEnd,
        onDragOver,
        onDragLeave,
        onDrop,
        isDragging,
        isDragOver,
    };
}
